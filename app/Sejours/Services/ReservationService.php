<?php

namespace App\Sejours\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Paiement\Services\PlancherAcompte;
use App\Paiement\Services\ReglagesPaiement;
use App\Parametres\Services\Reglages;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Unite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReservationService
{
    public const CHEVAUCHEMENT = '23P01';

    public function __construct(private TarifCalculateur $tarifs) {}

    /** Durée de garde d'une réservation en attente, lue dans les Paramètres (RG-2) : 2 heures par défaut. */
    public static function dureeGardeMinutes(): int
    {
        return Reglages::entier('reservation.duree_blocage_minutes');
    }

    /** Réservation provisoire : les dates sont gardées pour le client jusqu'à l'expiration. */
    public function garderDates(Utilisateur $client, Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart, int $personnes): Reservation
    {
        Gate::forUser($client)->authorize('create', Reservation::class);
        $this->verifierDates($arrivee, $depart);

        $unite->loadMissing('residence', 'tarifs');
        if (! $unite->active || ! $unite->residence->estVerifiee()) {
            throw new ReservationImpossible('Ce logement n\'est pas disponible à la réservation.');
        }
        if ($personnes < 1 || $personnes > $unite->capacite) {
            throw new ReservationImpossible("Ce logement accueille de 1 à {$unite->capacite} personnes.");
        }

        $total = $this->tarifs->total($unite, $arrivee, $depart);
        if ($total === null) {
            throw new ReservationImpossible('Ce logement n\'a pas encore de prix.');
        }
        [$acompte, $solde] = $this->repartition($unite, $total);

        $reservation = $this->enregistrer(fn () => Reservation::create([
            'unite_id' => $unite->id,
            'client_id' => $client->id,
            'date_arrivee' => $arrivee->toDateString(),
            'date_depart' => $depart->toDateString(),
            'nombre_personnes' => $personnes,
            'montant_total' => $total,
            'acompte' => $acompte,
            'solde' => $solde,
            'statut' => StatutReservation::EnAttente,
            'origine' => 'plateforme',
            'expire_le' => now()->addMinutes(self::dureeGardeMinutes()),
            'cree_par' => $client->id,
            // Conditions en vigueur à la création : un changement ultérieur des Paramètres ne les modifie pas.
            'commission_pourcentage' => ReglagesPaiement::commissionPourcentage(),
            'annulation_delai_heures' => ReglagesPaiement::delaiAnnulationGratuiteHeures(),
            'annulation_heure_arrivee' => ReglagesPaiement::heureArrivee(),
        ]), $unite, $arrivee, $depart);

        return $reservation;
    }

    /** RG-5 : réservation prise par téléphone, gratuite, sans commission ; elle bloque seulement le calendrier. */
    public function creerHorsPlateforme(Utilisateur $gerant, Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart, string $nomClient, ?string $telephoneClient = null): Reservation
    {
        $unite->loadMissing('residence');
        Gate::forUser($gerant)->authorize('creerHorsPlateforme', [Reservation::class, $unite]);
        $this->verifierDates($arrivee, $depart);

        if (trim($nomClient) === '') {
            throw new ReservationImpossible('Le nom du client est obligatoire.');
        }

        $reservation = $this->enregistrer(fn () => Reservation::create([
            'unite_id' => $unite->id,
            'client_id' => null,
            'nom_client' => trim($nomClient),
            'telephone_client' => $telephoneClient ?: null,
            'date_arrivee' => $arrivee->toDateString(),
            'date_depart' => $depart->toDateString(),
            'montant_total' => 0,
            'acompte' => 0,
            'solde' => 0,
            'statut' => StatutReservation::HorsPlateforme,
            'origine' => 'hors_plateforme',
            'cree_par' => $gerant->id,
        ]), $unite, $arrivee, $depart);

        Audit::enregistrer('reservation.hors_plateforme_creee', $reservation, [], $gerant);

        return $reservation;
    }

    public function changerStatut(Reservation $reservation, StatutReservation $cible): Reservation
    {
        $horsPlateforme = $reservation->origine === 'hors_plateforme';
        if (! $reservation->statut->peutPasserA($cible, $horsPlateforme)) {
            throw new ReservationImpossible("Une réservation « {$reservation->statut->libelle()} » ne peut pas passer à « {$cible->libelle()} ».");
        }

        try {
            DB::transaction(fn () => $reservation->update(['statut' => $cible]));
        } catch (QueryException $e) {
            throw $this->traduire($e, $reservation->unite, $reservation->date_arrivee, $reservation->date_depart);
        }

        return $reservation;
    }

    /** Libère les réservations « En attente » dont le délai est passé (RG-2). Renvoie le nombre libéré. */
    public function expirerLesReservations(): int
    {
        return DB::table('reservation')
            ->where('statut', StatutReservation::EnAttente->value)
            ->where('expire_le', '<=', now())
            ->update(['statut' => StatutReservation::Expiree->value, 'updated_at' => now()]);
    }

    /** Première période déjà occupée qui gêne ces dates, pour un message clair. @return array{debut: CarbonImmutable, fin: CarbonImmutable}|null */
    public function periodeEnConflit(Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): ?array
    {
        $ligne = DB::selectOne(
            "SELECT lower(periode) AS debut, upper(periode) AS fin FROM occupation_unite
             WHERE unite_id = ? AND periode && daterange(?, ?, '[)') ORDER BY lower(periode) LIMIT 1",
            [$unite->id, $arrivee->toDateString(), $depart->toDateString()],
        );

        return $ligne ? ['debut' => CarbonImmutable::parse($ligne->debut), 'fin' => CarbonImmutable::parse($ligne->fin)] : null;
    }

    private function verifierDates(CarbonInterface $arrivee, CarbonInterface $depart): void
    {
        if ($depart->copy()->startOfDay()->lte($arrivee->copy()->startOfDay())) {
            throw new ReservationImpossible('La date de départ doit être après la date d\'arrivée.');
        }
        if ($arrivee->copy()->startOfDay()->lt(now()->startOfDay())) {
            throw new ReservationImpossible('La date d\'arrivée ne peut pas être dans le passé.');
        }
    }

    /** @return array{0: int, 1: int} acompte à payer en ligne, solde à payer au gérant */
    public function repartition(Unite $unite, int $total): array
    {
        $residence = $unite->residence;
        if ($residence->mode_paiement === 'acompte' && PlancherAcompte::acompteEstPossible()) {
            $acompte = PlancherAcompte::montant($total, max((int) $residence->pourcentage_acompte, PlancherAcompte::pourcentageMinimum()));

            return [$acompte, $total - $acompte];
        }

        return [$total, 0]; // tout payer en ligne
    }

    private function enregistrer(callable $creation, Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): Reservation
    {
        try {
            return DB::transaction($creation); // point de sauvegarde : l'éventuelle transaction englobante reste utilisable
        } catch (QueryException $e) {
            throw $this->traduire($e, $unite, $arrivee, $depart);
        }
    }

    private function traduire(QueryException $e, Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): \Throwable
    {
        if ((string) $e->getCode() !== self::CHEVAUCHEMENT) {
            return $e;
        }

        $conflit = $this->periodeEnConflit($unite, $arrivee, $depart);

        return ReservationImpossible::datesIndisponibles($conflit
            ? 'Ce logement n\'est pas libre du '.$conflit['debut']->translatedFormat('j M').' au '.$conflit['fin']->translatedFormat('j M').'.'
            : null);
    }
}
