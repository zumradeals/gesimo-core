<?php

namespace App\Paiement\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Models\Paiement;
use App\Paiement\Models\Remboursement;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Paiement\Services\Fournisseur\RequeteRefusee;
use App\Sejours\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Remboursements : demandés (« en attente »), exécutés auprès du fournisseur, puis constatés par une écriture.
 * Si le fournisseur ne répond pas, la demande reste « en attente » et apparaît dans « À traiter » chez l'admin.
 */
class RemboursementService
{
    public function __construct(private FournisseurPaiement $fournisseur) {}

    public function demander(Paiement $paiement, ?Reservation $reservation, int $montant, string $motif): Remboursement
    {
        // Un seul remboursement par paiement (index unique) : redemander renvoie le même.
        return Remboursement::firstOrCreate(
            ['paiement_id' => $paiement->id],
            ['reservation_id' => $reservation?->id ?? $paiement->reservation_id, 'montant' => min($montant, $paiement->montant), 'motif' => $motif],
        );
    }

    public function executer(Remboursement $remboursement): Remboursement
    {
        $remboursement->refresh();
        if ($remboursement->statut !== 'en_attente') {
            return $remboursement;
        }
        $paiement = $remboursement->paiement;

        try {
            $resultat = $this->fournisseur->rembourser(
                $paiement->reference_geniuspay,
                $remboursement->montant === $paiement->montant ? null : $remboursement->montant, // sans montant = remboursement total
                $remboursement->motif,
            );
        } catch (FournisseurIndisponible|RequeteRefusee $e) {
            $remboursement->update(['tentatives' => $remboursement->tentatives + 1, 'derniere_erreur' => mb_substr($e->getMessage(), 0, 500)]);
            Alertes::creer('remboursement_en_echec', "Remboursement de {$remboursement->montant} FCFA non effectué : {$e->getMessage()}", $remboursement->reservation_id, $paiement->id, $remboursement->id);

            return $remboursement->fresh();
        }

        return $this->terminer($remboursement, $resultat->reference);
    }

    /** Constate un remboursement effectué (par l'API, par un message du fournisseur, ou à la main). */
    public function terminer(Remboursement $remboursement, string $reference, ?Utilisateur $auteur = null): Remboursement
    {
        DB::transaction(function () use ($remboursement, $reference, $auteur) {
            $verrouille = Remboursement::whereKey($remboursement->id)->lockForUpdate()->first();
            if ($verrouille->statut !== 'en_attente') {
                return;
            }
            $verrouille->update(['statut' => 'effectue', 'effectue_le' => now(), 'reference_fournisseur' => mb_substr($reference, 0, 190), 'derniere_erreur' => null]);
            Comptabilite::remboursement($verrouille, $auteur);
            Alertes::clore('remboursement_en_echec', $verrouille->paiement_id, $verrouille->id, $auteur?->id);
        });

        return $remboursement->fresh();
    }

    public function relancer(Remboursement $remboursement, Utilisateur $admin): Remboursement
    {
        Gate::forUser($admin)->authorize('gerer-paiements');
        Audit::enregistrer('remboursement.relance', $remboursement, [], $admin);

        return $this->executer($remboursement);
    }

    /** L'admin a remboursé le client lui-même (tableau de bord GeniusPay, Mobile Money…) et le consigne. */
    public function marquerFaitMain(Remboursement $remboursement, Utilisateur $admin, string $reference): Remboursement
    {
        Gate::forUser($admin)->authorize('gerer-paiements');
        $reference = trim($reference);
        if ($reference === '') {
            throw new PaiementImpossible('La référence du remboursement est obligatoire.');
        }
        Audit::enregistrer('remboursement.fait_a_la_main', $remboursement, ['reference' => $reference], $admin);

        return $this->terminer($remboursement, "manuel:{$reference}", $admin);
    }
}
