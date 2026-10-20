<?php

namespace App\Paiement\Services;

use App\Noyau\Models\Utilisateur;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Models\EvenementPaiement as EvenementEnregistre;
use App\Paiement\Models\Paiement;
use App\Paiement\Models\Remboursement;
use App\Paiement\Services\Fournisseur\DemandePaiement;
use App\Paiement\Services\Fournisseur\EtatPaiement;
use App\Paiement\Services\Fournisseur\EvenementPaiement;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\ReservationService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Paiement d'une réservation. Règle d'or : une réservation ne devient « Confirmée » que lorsque le SERVEUR
 * a vu un paiement réussi (message signé du fournisseur ET vérification directe auprès du fournisseur),
 * jamais sur la seule foi du retour du navigateur. La base de données refuse d'ailleurs toute confirmation sans paiement.
 */
class PaiementService
{
    public const MONTANT_MINIMUM = 200;

    public function __construct(private FournisseurPaiement $fournisseur, private RemboursementService $remboursements) {}

    /** Le paiement en ligne n'est ouvert que si les clés sont saisies ET la commission fixée (0 est permis). */
    public function disponible(): bool
    {
        return $this->fournisseur->estConfigure() && ReglagesPaiement::commissionPourcentage() !== null;
    }

    public function demarrer(Reservation $reservation, Utilisateur $client): Paiement
    {
        Gate::forUser($client)->authorize('payer', $reservation);
        $reservation->loadMissing('unite.residence');

        if ($reservation->statut !== StatutReservation::EnAttente || $reservation->expire_le === null || $reservation->expire_le->isPast()) {
            throw new PaiementImpossible('Le délai de garde est terminé : ces dates ne sont plus réservées pour vous. Refaites une réservation.');
        }
        if (! $this->disponible()) {
            throw new PaiementImpossible('Le paiement en ligne n\'est pas encore disponible. Vos dates restent gardées : réessayez plus tard.');
        }
        if ($reservation->acompte < self::MONTANT_MINIMUM) {
            throw new PaiementImpossible('Ce montant est trop faible pour un paiement en ligne.');
        }

        // Un lien de paiement encore valable est réutilisé : jamais deux paiements ouverts pour la même réservation.
        $ouvert = Paiement::where('reservation_id', $reservation->id)->where('statut', 'en_attente')->where('mode', $this->fournisseur->mode())
            ->whereNotNull('checkout_url')->where('lien_expire_le', '>', now())->latest('id')->first();
        if ($ouvert !== null) {
            return $ouvert;
        }

        $tentative = Paiement::where('reservation_id', $reservation->id)->count() + 1;
        $unite = $reservation->unite;
        $retour = route('paiement.retour', $reservation);

        $cree = $this->fournisseur->creerPaiement(new DemandePaiement(
            montant: $reservation->acompte,
            description: "Réservation {$reservation->id} : {$unite->nom} ({$unite->residence->nom}) du {$reservation->date_arrivee->format('d/m/Y')} au {$reservation->date_depart->format('d/m/Y')}",
            referenceExterne: "gesimo-resa-{$reservation->id}-{$tentative}",
            nomClient: $client->nom,
            emailClient: $client->email,
            telephoneClient: '+225'.$client->telephone,
            urlSucces: $retour,
            urlErreur: $retour,
            metadata: ['reservation_id' => $reservation->id, 'application' => 'gesimo-sejours'],
        ));

        try {
            return DB::transaction(fn () => Paiement::create([
                'reservation_id' => $reservation->id,
                'reference_geniuspay' => $cree->reference,
                'reference_externe' => "gesimo-resa-{$reservation->id}-{$tentative}",
                'montant' => $reservation->acompte,
                'frais' => min($cree->frais, $reservation->acompte),
                'mode' => $this->fournisseur->mode(),
                'checkout_url' => $cree->urlPaiement,
                'lien_expire_le' => $cree->expireLe,
                'donnees_brutes' => $cree->brut,
            ]));
        } catch (UniqueConstraintViolationException) {
            return Paiement::where('reference_geniuspay', $cree->reference)->firstOrFail(); // même transaction rejouée
        }
    }

    /** Interroge directement le fournisseur (page de retour) : c'est le serveur qui juge, pas le navigateur. */
    public function verifier(Paiement $paiement): Paiement
    {
        if ($paiement->statut !== 'en_attente') {
            return $paiement;
        }

        $etat = $this->fournisseur->lirePaiement($paiement->reference_geniuspay);
        if ($etat->statut === EtatPaiement::REUSSI) {
            $this->confirmerApresVerification($paiement, $etat);
        } elseif ($etat->statut === EtatPaiement::ECHOUE) {
            Paiement::whereKey($paiement->id)->where('statut', 'en_attente')->update(['statut' => 'echoue', 'updated_at' => now()]);
        }

        return $paiement->fresh();
    }

    /**
     * Traite un message du fournisseur dont la signature est déjà vérifiée. Idempotent : le même message reçu
     * plusieurs fois ne produit qu'un seul effet. Lève FournisseurIndisponible si on doit demander un nouvel envoi.
     */
    public function traiterEvenement(EvenementPaiement $evenement): string
    {
        $reference = $evenement->reference;
        $paiement = $reference === null ? null : Paiement::where('reference_geniuspay', $reference)->first();

        if ($paiement === null) {
            $nouveau = $this->enregistrerEvenement($evenement, 'paiement_inconnu');
            if ($nouveau && $evenement->type === EvenementPaiement::REUSSI) {
                Alertes::creer('paiement_inconnu', "Paiement {$reference} signalé comme réussi par le fournisseur mais inconnu de GESIMO.");
            }

            return 'paiement_inconnu';
        }

        switch ($evenement->type) {
            case EvenementPaiement::REUSSI:
                $etat = $this->fournisseur->lirePaiement($paiement->reference_geniuspay); // vérification directe, pas seulement le message
                if ($etat->statut === EtatPaiement::REUSSI) {
                    return $this->confirmerApresVerification($paiement, $etat, $evenement);
                }
                if ($etat->statut === EtatPaiement::ECHOUE) {
                    return $this->marquerEchoue($paiement, $evenement);
                }
                if ($etat->statut === EtatPaiement::REMBOURSE) {
                    return $this->enregistrerSeulement($evenement, 'deja_rembourse');
                }

                throw new FournisseurIndisponible('Le fournisseur ne confirme pas encore ce paiement : nouvel envoi demandé.');
            case EvenementPaiement::ECHOUE:
                return $this->marquerEchoue($paiement, $evenement);

            case EvenementPaiement::REMBOURSE:
                return $this->constaterRemboursement($paiement, $evenement);

            default:
                return $this->enregistrerSeulement($evenement, 'ignore');
        }
    }

    // --- Cœur : application d'un paiement réussi ----------------------------------------------------------

    private function confirmerApresVerification(Paiement $paiement, EtatPaiement $etat, ?EvenementPaiement $evenement = null): string
    {
        if ($etat->montant !== null && $etat->montant !== $paiement->montant) {
            // Argent reçu différent de l'attendu : on ne confirme rien automatiquement, l'admin tranche.
            if ($evenement !== null) {
                $this->enregistrerEvenement($evenement, 'montant_different');
            }
            Alertes::creer('montant_different', "Paiement {$paiement->reference_geniuspay} : le fournisseur annonce {$etat->montant} FCFA, GESIMO attendait {$paiement->montant} FCFA. Aucune confirmation automatique.", $paiement->reservation_id, $paiement->id);

            return 'montant_different';
        }

        /** @var list<Remboursement> $aExecuter */
        $aExecuter = [];
        $resultat = DB::transaction(function () use ($paiement, $evenement, &$aExecuter) {
            if ($evenement !== null && ! $this->enregistrerEvenement($evenement, null)) {
                return 'doublon';
            }

            $verrouille = Paiement::whereKey($paiement->id)->lockForUpdate()->first();
            if ($verrouille->statut !== 'en_attente') {
                $this->noterResultat($evenement, 'deja_traite');

                return 'deja_traite';
            }

            $resultat = $this->appliquerReussite($verrouille, $aExecuter);
            $this->noterResultat($evenement, $resultat);

            return $resultat;
        });

        foreach ($aExecuter as $remboursement) {
            $this->remboursements->executer($remboursement); // hors transaction : appel au fournisseur
        }

        return $resultat;
    }

    /** @param list<Remboursement> $aExecuter */
    private function appliquerReussite(Paiement $paiement, array &$aExecuter): string
    {
        $reservation = Reservation::whereKey($paiement->reservation_id)->lockForUpdate()->first();
        $paiement->statut = 'reussi';
        $paiement->save();
        Comptabilite::encaissement($paiement);

        if ($reservation === null) {
            return 'sans_reservation';
        }

        switch ($reservation->statut) {
            case StatutReservation::EnAttente:
                $this->confirmer($reservation);

                return 'confirmee';

            case StatutReservation::Expiree:
                // Paiement arrivé après la fin du délai : on tente de redonner les dates, sinon on rembourse.
                try {
                    DB::transaction(fn () => $this->confirmer($reservation));
                } catch (QueryException $e) {
                    if ((string) $e->getCode() !== ReservationService::CHEVAUCHEMENT) {
                        throw $e;
                    }
                    $aExecuter[] = $this->remboursements->demander($paiement, $reservation, $paiement->montant, 'Paiement tardif : dates reprises');
                    Alertes::creer('paiement_tardif_rembourse', "Réservation #{$reservation->id} : paiement de {$paiement->montant} FCFA reçu après l'expiration, dates reprises par un autre client. Remboursement demandé.", $reservation->id, $paiement->id);

                    return 'rembourse_tardif';
                }
                Alertes::creer('paiement_tardif_reattribue', "Réservation #{$reservation->id} : paiement reçu après l'expiration, dates encore libres : réservation confirmée.", $reservation->id, $paiement->id);

                return 'confirmee_tardive';

            case StatutReservation::Annulee:
            case StatutReservation::Litige:
                $aExecuter[] = $this->remboursements->demander($paiement, $reservation, $paiement->montant, 'Paiement reçu pour une réservation annulée');
                Alertes::creer('paiement_apres_annulation', "Réservation #{$reservation->id} annulée : paiement de {$paiement->montant} FCFA reçu. Remboursement demandé.", $reservation->id, $paiement->id);

                return 'rembourse_annulee';

            default: // déjà confirmée (ou plus loin) : un second paiement est un doublon
                $aExecuter[] = $this->remboursements->demander($paiement, $reservation, $paiement->montant, 'Double paiement');
                Alertes::creer('double_paiement', "Réservation #{$reservation->id} : second paiement de {$paiement->montant} FCFA. Remboursement demandé.", $reservation->id, $paiement->id);

                return 'rembourse_doublon';
        }
    }

    private function confirmer(Reservation $reservation): void
    {
        // Le taux de commission est figé à la confirmation : une modification ultérieure ne change pas cette réservation.
        $reservation->update([
            'statut' => StatutReservation::Confirmee,
            'expire_le' => null,
            'commission_pourcentage' => $reservation->commission_pourcentage ?? ReglagesPaiement::commissionPourcentage() ?? 0,
        ]);
    }

    private function marquerEchoue(Paiement $paiement, EvenementPaiement $evenement): string
    {
        return DB::transaction(function () use ($paiement, $evenement) {
            if (! $this->enregistrerEvenement($evenement, 'echoue')) {
                return 'doublon';
            }
            Paiement::whereKey($paiement->id)->where('statut', 'en_attente')->update(['statut' => 'echoue', 'updated_at' => now()]);

            return 'echoue';
        });
    }

    private function constaterRemboursement(Paiement $paiement, EvenementPaiement $evenement): string
    {
        return DB::transaction(function () use ($paiement, $evenement) {
            if (! $this->enregistrerEvenement($evenement, 'rembourse')) {
                return 'doublon';
            }
            $remboursement = Remboursement::where('paiement_id', $paiement->id)->where('statut', 'en_attente')->first();
            if ($remboursement === null) {
                Alertes::creer('remboursement_externe', "Paiement {$paiement->reference_geniuspay} remboursé chez le fournisseur sans demande de GESIMO : vérifier.", $paiement->reservation_id, $paiement->id);

                return 'rembourse_externe';
            }
            $this->remboursements->terminer($remboursement, 'webhook');

            return 'rembourse';
        });
    }

    private function enregistrerSeulement(EvenementPaiement $evenement, string $resultat): string
    {
        $this->enregistrerEvenement($evenement, $resultat);

        return $resultat;
    }

    /** Vrai si le message est nouveau, faux si le même message a déjà été traité. Corps sans aucun secret. */
    private function enregistrerEvenement(EvenementPaiement $evenement, ?string $resultat): bool
    {
        try {
            DB::transaction(fn () => EvenementEnregistre::create([
                'reference' => $evenement->reference,
                'evenement' => $evenement->nomFournisseur,
                'environnement' => $evenement->environnement,
                'signature_valide' => true,
                'corps' => $evenement->corps,
                'resultat' => $resultat,
            ]));

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function noterResultat(?EvenementPaiement $evenement, string $resultat): void
    {
        if ($evenement === null) {
            return;
        }
        EvenementEnregistre::where('reference', $evenement->reference)->where('evenement', $evenement->nomFournisseur)
            ->where('signature_valide', true)->update(['resultat' => $resultat]);
    }
}
