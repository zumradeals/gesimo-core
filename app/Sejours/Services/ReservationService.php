<?php

namespace App\Sejours\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Paiement\Services\PlancherAcompte;
use App\Paiement\Services\ReglagesPaiement;
use App\Parametres\Services\Reglages;
use App\Sejours\Enums\HeureArriveePrevue;
use App\Sejours\Enums\MotifSejour;
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

    /** PostgreSQL a interrompu l'une de deux transactions qui se bloquaient mutuellement (deadlock_detected). */
    public const INTERBLOCAGE = '40P01';

    public function __construct(private TarifCalculateur $tarifs) {}

    /** Durée de garde d'une réservation en attente, lue dans les Paramètres (RG-2) : 2 heures par défaut. */
    public static function dureeGardeMinutes(): int
    {
        return Reglages::entier('reservation.duree_blocage_minutes');
    }

    /**
     * Réservation provisoire : les dates sont gardées pour le client jusqu'à l'expiration.
     *
     * @param  array{nom?: string, telephone?: string, motif?: string, motif_precision?: ?string, heure_arrivee?: ?string, message?: ?string, accord_le?: ?CarbonInterface}|null  $voyageur
     *                                                                                                                                                                                       Informations demandées à l'étape « Vos informations » (page de réservation) ; null pour les appels internes sans formulaire.
     */
    public function garderDates(Utilisateur $client, Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart, int $personnes, ?array $voyageur = null): Reservation
    {
        Gate::forUser($client)->authorize('create', Reservation::class);
        $this->verifierDates($arrivee, $depart);
        $this->verifierDuree($arrivee, $depart);
        $infos = $voyageur === null ? [] : $this->informationsVoyageur($voyageur);

        $unite->loadMissing('residence', 'tarifs');
        if (! $unite->active || ! $unite->residence->estPubliee()) {
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

        // Un double appui sur « Réserver » (ou un rechargement) ne crée pas une seconde réservation : on reprend la première.
        if (($deja = $this->reservationIdentiqueEnAttente($client, $unite, $arrivee, $depart)) !== null) {
            return $this->completerInformations($deja, $infos);
        }

        try {
            $reservation = $this->enregistrer(fn () => Reservation::create($infos + [
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
                'heure_depart' => ReglesSejour::heureDepart(),
            ]), $unite, $arrivee, $depart);
        } catch (ReservationImpossible $e) {
            // Deux envois en même temps : la base a refusé le second ; s'il vient de la même personne, on lui rend la première.
            if (($deja = $this->reservationIdentiqueEnAttente($client, $unite, $arrivee, $depart)) !== null) {
                return $this->completerInformations($deja, $infos);
            }
            throw $e;
        }

        return $reservation;
    }

    /** Un second envoi du formulaire met à jour les informations de la même réservation (jamais les montants). */
    private function completerInformations(Reservation $reservation, array $infos): Reservation
    {
        if ($infos !== []) {
            $reservation->update($infos);
        }

        return $reservation;
    }

    /**
     * Informations du voyageur contrôlées et prêtes à être enregistrées.
     *
     * @return array<string, mixed>
     */
    private function informationsVoyageur(array $voyageur): array
    {
        $nom = trim((string) ($voyageur['nom'] ?? ''));
        $motif = MotifSejour::tryFrom((string) ($voyageur['motif'] ?? ''));
        $precision = trim((string) ($voyageur['motif_precision'] ?? ''));
        $heure = HeureArriveePrevue::tryFrom((string) ($voyageur['heure_arrivee'] ?? ''));
        $message = trim((string) ($voyageur['message'] ?? ''));

        if ($nom === '') {
            throw new ReservationImpossible('Indiquez votre nom comme sur votre pièce d\'identité.');
        }
        if ($motif === null) {
            throw new ReservationImpossible('Choisissez le motif de votre séjour.');
        }
        if ($motif === MotifSejour::Autre && $precision === '') {
            throw new ReservationImpossible('Précisez en quelques mots le motif de votre séjour.');
        }
        if (($voyageur['accord_le'] ?? null) === null) {
            throw new ReservationImpossible('Vous devez accepter le règlement de la résidence et les conditions générales.');
        }

        return [
            'nom_client' => mb_substr($nom, 0, 255),
            'telephone_client' => ($t = trim((string) ($voyageur['telephone'] ?? ''))) === '' ? null : mb_substr($t, 0, 30),
            'motif_sejour' => $motif->value,
            'motif_precision' => $motif === MotifSejour::Autre ? mb_substr($precision, 0, 120) : null,
            'heure_arrivee_prevue' => $heure?->value,
            'message_gerant' => $message === '' ? null : mb_substr($message, 0, 500),
            'conditions_acceptees_le' => $voyageur['accord_le'],
        ];
    }

    private function reservationIdentiqueEnAttente(Utilisateur $client, Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): ?Reservation
    {
        return Reservation::where('client_id', $client->id)->where('unite_id', $unite->id)
            ->whereDate('date_arrivee', $arrivee->toDateString())->whereDate('date_depart', $depart->toDateString())
            ->where('statut', StatutReservation::EnAttente->value)->where('expire_le', '>', now())->latest('id')->first();
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

    /** Durée de la réservation en ligne : entre le séjour minimum et le séjour maximum réglés dans les Paramètres. */
    private function verifierDuree(CarbonInterface $arrivee, CarbonInterface $depart): void
    {
        $nuits = (int) $arrivee->copy()->startOfDay()->diffInDays($depart->copy()->startOfDay());
        if ($nuits < ReglesSejour::nuitsMin()) {
            $min = ReglesSejour::nuitsMin();
            throw new ReservationImpossible("Le séjour minimum est de {$min} nuit".($min > 1 ? 's' : '').'.');
        }
        if ($nuits > ReglesSejour::nuitsMax()) {
            $max = ReglesSejour::nuitsMax();
            throw new ReservationImpossible("Au-delà de {$max} nuits, la réservation en ligne n'est pas possible : contactez-nous pour organiser un long séjour.");
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
            // Point de sauvegarde : l'éventuelle transaction englobante reste utilisable. Si deux réservations des mêmes dates
            // se croisent, PostgreSQL peut en interrompre une (« deadlock ») : Laravel la rejoue, et elle reçoit alors le refus normal.
            return DB::transaction($creation, 5);
        } catch (QueryException $e) {
            throw $this->traduire($e, $unite, $arrivee, $depart);
        }
    }

    private function traduire(QueryException $e, Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): \Throwable
    {
        if ((string) $e->getCode() === self::INTERBLOCAGE) {
            return new ReservationImpossible('Beaucoup de personnes réservent en même temps : réessayez dans un instant. Rien n\'a été réservé.');
        }
        if ((string) $e->getCode() !== self::CHEVAUCHEMENT) {
            return $e;
        }

        $conflit = $this->periodeEnConflit($unite, $arrivee, $depart);

        return ReservationImpossible::datesIndisponibles($conflit
            ? 'Ce logement n\'est pas libre du '.$conflit['debut']->translatedFormat('j M').' au '.$conflit['fin']->translatedFormat('j M').'.'
            : null);
    }
}
