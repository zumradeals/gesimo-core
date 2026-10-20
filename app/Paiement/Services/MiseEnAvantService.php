<?php

namespace App\Paiement\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Models\MiseEnAvant;
use App\Paiement\Models\Paiement;
use App\Parametres\Services\Parametres;
use App\Sejours\Models\Residence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Mise en avant payante (RG-18, RG-19) : le gérant choisit une offre (durée et prix venant des Paramètres), paie à GESIMO,
 * l'annonce passe en tête de recherche avec la mention « Sponsorisé » pendant la période, puis redevient normale toute seule.
 * L'argent est entièrement à GESIMO : pas de commission, pas de part de gérant, pas de remboursement automatique.
 */
class MiseEnAvantService
{
    /** @return list<array{nom: string, jours: int, prix: int}> */
    public static function offres(): array
    {
        $liste = json_decode((string) Parametres::obtenir('mise_en_avant.offres'), true);

        return is_array($liste) ? array_values(array_filter($liste, fn ($o) => isset($o['nom'], $o['jours'], $o['prix']))) : [];
    }

    /** Crée la demande d'achat (« en attente de paiement ») avec le prix et la durée EN VIGUEUR aujourd'hui. */
    public function creerAchat(Utilisateur $gerant, Residence $residence, int $indexOffre): MiseEnAvant
    {
        Gate::forUser($gerant)->authorize('update', $residence);
        if (! $residence->estVerifiee()) {
            throw new PaiementImpossible('Seule une résidence vérifiée par GESIMO peut être mise en avant.');
        }
        $offre = self::offres()[$indexOffre] ?? throw new PaiementImpossible('Cette offre n\'existe plus : choisissez-en une autre.');

        $aujourdhui = CarbonImmutable::today();

        return MiseEnAvant::create([
            'residence_id' => $residence->id,
            'date_debut' => $aujourdhui->toDateString(), // provisoire : fixée à la confirmation du paiement
            'date_fin' => $aujourdhui->addDays($offre['jours'] - 1)->toDateString(),
            'prix' => $offre['prix'],
            'offre' => $offre['nom'],
            'jours' => $offre['jours'],
            'cree_par' => $gerant->id,
        ]);
    }

    /**
     * Appelée quand le paiement est confirmé (déjà vérifié auprès du fournisseur). Dans la transaction du paiement.
     * Renvoie le résultat pour le journal des messages du fournisseur.
     */
    public function activer(Paiement $paiement): string
    {
        Comptabilite::miseEnAvant($paiement);

        $mea = MiseEnAvant::whereKey($paiement->mise_en_avant_id)->lockForUpdate()->first();
        if ($mea === null) {
            return 'sans_mise_en_avant';
        }
        if (! in_array($mea->statut, ['en_attente_paiement', 'annulee'], true)) {
            // Déjà payée : ce second paiement est un doublon. Aucun remboursement automatique : l'administrateur décide.
            Alertes::creer('double_paiement', "Mise en avant #{$mea->id} déjà active : second paiement de {$paiement->montant} FCFA reçu. Remboursement à faire à la main (hors plateforme).", null, $paiement->id);

            return 'doublon_mise_en_avant';
        }

        // Si la résidence a déjà une période en cours ou programmée, la nouvelle commence juste après.
        $derniere = MiseEnAvant::where('residence_id', $mea->residence_id)->where('id', '!=', $mea->id)->whereIn('statut', ['active', 'suspendue'])->max('date_fin');
        $debut = CarbonImmutable::today();
        if ($derniere !== null && CarbonImmutable::parse($derniere)->gte($debut)) {
            $debut = CarbonImmutable::parse($derniere)->addDay();
        }
        $mea->update([
            'statut' => 'active',
            'date_debut' => $debut->toDateString(),
            'date_fin' => $debut->addDays(((int) $mea->jours ?: 1) - 1)->toDateString(),
        ]);
        Audit::enregistrer('mise_en_avant.activee', $mea, ['residence' => $mea->residence_id, 'prix' => $mea->prix]);

        return 'mise_en_avant_activee';
    }

    public function suspendre(MiseEnAvant $mea, Utilisateur $admin, string $motif): MiseEnAvant
    {
        Gate::forUser($admin)->authorize('gerer-mises-en-avant');
        $motif = trim($motif);
        if (mb_strlen($motif) < 5) {
            throw new PaiementImpossible('Indiquez pourquoi vous suspendez (5 caractères au minimum).');
        }
        $pris = MiseEnAvant::whereKey($mea->id)->where('statut', 'active')->update(['statut' => 'suspendue', 'suspendue_le' => now(), 'suspendue_par' => $admin->id, 'suspension_motif' => mb_substr($motif, 0, 500), 'updated_at' => now()]);
        if ($pris !== 1) {
            throw new PaiementImpossible('Seule une mise en avant active peut être suspendue.');
        }
        Audit::enregistrer('mise_en_avant.suspendue', $mea, ['motif' => $motif], $admin);

        return $mea->fresh();
    }

    /** Reprend jusqu'à la date de fin d'origine, sans prolongation. */
    public function reprendre(MiseEnAvant $mea, Utilisateur $admin): MiseEnAvant
    {
        Gate::forUser($admin)->authorize('gerer-mises-en-avant');
        $pris = MiseEnAvant::whereKey($mea->id)->where('statut', 'suspendue')->update(['statut' => 'active', 'suspendue_le' => null, 'suspendue_par' => null, 'suspension_motif' => null, 'updated_at' => now()]);
        if ($pris !== 1) {
            throw new PaiementImpossible('Cette mise en avant n\'est pas suspendue.');
        }
        Audit::enregistrer('mise_en_avant.reprise', $mea, [], $admin);

        return $mea->fresh();
    }

    /**
     * Tâche planifiée : les périodes dépassées passent « Terminée » ; les achats jamais payés depuis 2 jours sont abandonnés.
     * L'annonce redevenait déjà normale à la fin de la période (la visibilité dépend des dates) : ceci ne fait que ranger.
     *
     * @return array{terminees: int, abandonnees: int}
     */
    public function ranger(): array
    {
        $jour = now()->toDateString();

        return [
            'terminees' => MiseEnAvant::whereIn('statut', ['active', 'suspendue'])->whereDate('date_fin', '<', $jour)->update(['statut' => 'terminee', 'updated_at' => now()]),
            'abandonnees' => MiseEnAvant::where('statut', 'en_attente_paiement')->where('created_at', '<', now()->subDays(2))->update(['statut' => 'annulee', 'updated_at' => now()]),
        ];
    }

    /** Identifiants des résidences mises en avant aujourd'hui. @return list<int> */
    public static function residencesEnAvant(): array
    {
        return MiseEnAvant::enCours()->pluck('residence_id')->unique()->values()->all();
    }
}
