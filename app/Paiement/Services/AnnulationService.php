<?php

namespace App\Paiement\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Paiement\Models\Paiement;
use App\Paiement\Models\Remboursement;
use App\Paiement\Models\Reversement;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\ReservationService;
use App\Sejours\Services\TarifCalculateur;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Annulations et remboursements (RG-6 à RG-10), politique « flexible » :
 *  - le client annule plus de 24 h avant l'arrivée : remboursement intégral ;
 *  - moins de 24 h avant : une nuit retenue (la première), le reste remboursé ;
 *  - le gérant annule, ou le logement est indisponible : remboursement intégral au client (RG-10) ;
 *  - client absent sans annulation : aucun remboursement (RG-8).
 * Pour une nuit retenue, la nuit est reversée au gérant et la commission ne porte que sur cette nuit (RG-17).
 * Le délai (24 h) et l'heure d'arrivée supposée (14 h) sont des réglages.
 */
class AnnulationService
{
    public const CAS_INTEGRAL = 'integral';

    public const CAS_NUIT_RETENUE = 'nuit_retenue';

    public const CAS_AUCUN = 'aucun';

    public function __construct(
        private RemboursementService $remboursements,
        private ReservationService $reservations,
        private TarifCalculateur $tarifs,
    ) {}

    /**
     * Ce qui sera remboursé si cette personne annule maintenant.
     *
     * @return array{cas: string, paye: int, rembourse: int, retenu: int, nuit: int, limite: CarbonImmutable}
     */
    public function apercu(Reservation $reservation, Utilisateur $acteur, ?CarbonInterface $maintenant = null): array
    {
        $maintenant ??= now();
        $reservation->loadMissing('unite.tarifs');
        $paye = Comptabilite::encaisse($reservation);
        $limite = CarbonImmutable::parse($reservation->date_arrivee->toDateString().' '.ReglagesPaiement::heureArrivee())
            ->subHours(ReglagesPaiement::delaiAnnulationGratuiteHeures());
        $nuit = (int) ($this->tarifs->detail($reservation->unite, $reservation->date_arrivee, $reservation->date_depart)[0]['prix'] ?? 0);

        $parLeClient = $acteur->id === $reservation->client_id;
        if ($paye === 0) {
            $cas = self::CAS_AUCUN;
            $retenu = 0;
        } elseif (! $parLeClient || $maintenant->lessThanOrEqualTo($limite)) {
            $cas = self::CAS_INTEGRAL; // gérant / admin : toujours intégral (RG-10)
            $retenu = 0;
        } else {
            $cas = self::CAS_NUIT_RETENUE;
            $retenu = min($nuit, $paye);
        }

        return ['cas' => $cas, 'paye' => $paye, 'rembourse' => $paye - $retenu, 'retenu' => $retenu, 'nuit' => $nuit, 'limite' => $limite];
    }

    public function annuler(Reservation $reservation, Utilisateur $acteur): Reservation
    {
        Gate::forUser($acteur)->authorize('annuler', $reservation);
        $reservation->loadMissing('unite.residence');
        $motif = $acteur->id === $reservation->client_id ? 'client' : 'gerant';

        if ($reservation->statut === StatutReservation::EnAttente || $reservation->origine === 'hors_plateforme') {
            if (! $reservation->statut->peutPasserA(StatutReservation::Annulee, $reservation->origine === 'hors_plateforme')) {
                throw new ReservationImpossible('Cette réservation ne peut plus être annulée.');
            }
            $this->reservations->changerStatut($reservation, StatutReservation::Annulee);
            $reservation->update(['annulation_motif' => $motif, 'annulee_le' => now()]);
            Audit::enregistrer('reservation.annulee', $reservation, ['motif' => $motif], $acteur);

            return $reservation->fresh();
        }

        if ($reservation->statut !== StatutReservation::Confirmee) {
            throw new ReservationImpossible('Cette réservation ne peut plus être annulée ici.');
        }
        if ($reservation->date_depart->startOfDay()->lte(now()->startOfDay()) || $reservation->date_arrivee->startOfDay()->lt(now()->startOfDay()) && $motif === 'client') {
            throw new ReservationImpossible('Cette réservation ne peut plus être annulée (le séjour a commencé).');
        }

        /** @var list<Remboursement> $aExecuter */
        $aExecuter = [];
        DB::transaction(function () use ($reservation, $acteur, $motif, &$aExecuter) {
            $verrouillee = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
            if ($verrouillee->statut !== StatutReservation::Confirmee) {
                throw new ReservationImpossible('Cette réservation vient de changer : actualisez la page.');
            }
            $verrouillee->setRelation('unite', $reservation->unite);
            $apercu = $this->apercu($verrouillee, $acteur);
            $paiement = Paiement::where('reservation_id', $verrouillee->id)->where('statut', 'reussi')->latest('id')->first();

            $verrouillee->update(['statut' => StatutReservation::Annulee, 'annulation_motif' => $motif, 'annulee_le' => now()]);

            if ($apercu['retenu'] > 0) {
                $this->acquerir($verrouillee, $apercu['retenu'], $apercu['nuit'], $acteur, 'Nuit retenue (annulation tardive)');
            }
            if ($apercu['rembourse'] > 0 && $paiement !== null) {
                $aExecuter[] = $this->remboursements->demander($paiement, $verrouillee, $apercu['rembourse'], $motif === 'client' ? 'Annulation par le client' : 'Annulation par le gérant');
            }
            Audit::enregistrer('reservation.annulee', $verrouillee, ['motif' => $motif, 'rembourse' => $apercu['rembourse'], 'retenu' => $apercu['retenu']], $acteur);
        });

        foreach ($aExecuter as $remboursement) {
            $this->remboursements->executer($remboursement);
        }

        return $reservation->fresh();
    }

    /** « Client absent » (RG-8) : aucun remboursement ; tout ce qui a été encaissé est acquis (commission + part du gérant). */
    public function declarerAbsent(Reservation $reservation, Utilisateur $acteur): Reservation
    {
        Gate::forUser($acteur)->authorize('enregistrerArrivee', $reservation);

        DB::transaction(function () use ($reservation, $acteur) {
            $verrouillee = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
            if ($verrouillee->statut !== StatutReservation::Confirmee) {
                throw new ReservationImpossible('Seule une réservation confirmée peut être marquée « client absent ».');
            }
            if (now()->startOfDay()->lt($verrouillee->date_arrivee->startOfDay())) {
                throw new ReservationImpossible('On ne peut déclarer un client absent qu\'à partir de la date d\'arrivée prévue.');
            }
            $verrouillee->update(['statut' => StatutReservation::Annulee, 'annulation_motif' => 'absence', 'annulee_le' => now()]);
            $this->acquerir($verrouillee, Comptabilite::encaisse($verrouillee), $verrouillee->montant_total, $acteur, 'Client absent');
            Audit::enregistrer('reservation.client_absent', $verrouillee, [], $acteur);
        });

        return $reservation->fresh();
    }

    /**
     * Constate qu'une somme est « acquise » : commission (sur la base donnée) + part du gérant, et crée le
     * reversement à faire. Utilisé à l'arrivée, pour une nuit retenue et pour une absence. À appeler dans une transaction.
     */
    public function acquerir(Reservation $reservation, int $acquis, int $baseCommission, ?Utilisateur $auteur, string $motif): ?Reversement
    {
        if ($acquis <= 0) {
            return null;
        }
        $reservation->loadMissing('unite.residence');
        $commission = Comptabilite::calculerCommission($reservation, $baseCommission, $acquis);
        $part = $acquis - $commission;

        Comptabilite::commission($reservation, $commission, $auteur, $motif);
        Comptabilite::partGerant($reservation, $part, $auteur, $motif);

        if ($part <= 0) {
            return null;
        }

        return Reversement::firstOrCreate(
            ['reservation_id' => $reservation->id],
            ['gerant_id' => $reservation->unite->residence->gerant_id, 'montant' => $part],
        );
    }
}
