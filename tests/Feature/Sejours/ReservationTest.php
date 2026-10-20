<?php

use App\Noyau\Enums\Role;
use App\Paiement\Services\AnnulationService;
use App\Parametres\Services\Parametres;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\BlocageService;
use App\Sejours\Services\ReservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function service(): ReservationService
{
    return app(ReservationService::class);
}

it('une réservation provisoire passe « En attente » avec une expiration de 2 heures par défaut', function () {
    $unite = creerLogement();
    $client = creerUtilisateur(Role::Client);
    $vendredi = prochainVendredi();

    $reservation = service()->garderDates($client, $unite, $vendredi, $vendredi->addDays(3), 2);

    expect($reservation->statut)->toBe(StatutReservation::EnAttente)
        ->and($reservation->montant_total)->toBe(55000)
        ->and($reservation->origine)->toBe('plateforme')
        ->and($reservation->nombre_personnes)->toBe(2)
        ->and($reservation->expire_le->between(now()->addMinutes(119), now()->addMinutes(121)))->toBeTrue();
});

it('lit la durée de garde dans les réglages', function () {
    Parametres::definir('reservation.duree_blocage_minutes', '45');
    $reservation = service()->garderDates(creerUtilisateur(Role::Client), creerLogement(), prochainVendredi(), prochainVendredi()->addDays(2), 1);

    expect($reservation->expire_le->between(now()->addMinutes(44), now()->addMinutes(46)))->toBeTrue();
});

it('répartit acompte et solde en FCFA entiers, ou tout en ligne', function () {
    $vendredi = prochainVendredi();
    $client = creerUtilisateur(Role::Client);

    // Par défaut (commission non réglée) : tout payer en ligne, même si la résidence demande un acompte.
    $acompte = creerLogement(15000, 20000, ['mode_paiement' => 'acompte', 'pourcentage_acompte' => 30]);
    $r1 = service()->garderDates($client, $acompte, $vendredi, $vendredi->addDays(3), 1);
    expect([$r1->acompte, $r1->solde])->toBe([55000, 0]);

    // Commission et frais réglés : acompte = max(pourcentage choisi, plancher), arrondi au-dessus.
    Parametres::definir('commission.pourcentage', '10');
    Parametres::definir('paiement.frais_pourcentage', '2.5');
    $r2 = service()->garderDates($client, creerLogement(15000, 20000, ['mode_paiement' => 'acompte', 'pourcentage_acompte' => 30]), $vendredi, $vendredi->addDays(3), 1);
    expect([$r2->acompte, $r2->solde])->toBe([16500, 38500]);

    $r3 = service()->garderDates($client, creerLogement(15000, 20000, ['mode_paiement' => 'acompte', 'pourcentage_acompte' => 5]), $vendredi, $vendredi->addDays(3), 1);
    expect($r3->acompte)->toBe(7150); // plancher 13 % : 55 000 × 13 %
});

it('refuse des dates déjà prises avec un message clair, jamais deux fois les mêmes dates', function () {
    $unite = creerLogement();
    $vendredi = prochainVendredi();
    service()->garderDates(creerUtilisateur(Role::Client), $unite, $vendredi, $vendredi->addDays(3), 1);

    expect(fn () => service()->garderDates(creerUtilisateur(Role::Client), $unite, $vendredi->addDay(), $vendredi->addDays(4), 1))
        ->toThrow(ReservationImpossible::class, 'Ce logement n\'est pas libre du');
    expect(Reservation::count())->toBe(1);
});

it('refuse les dates passées, inversées, une capacité dépassée et un logement non vérifié', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement(15000, 20000, [], 2);
    $vendredi = prochainVendredi();

    expect(fn () => service()->garderDates($client, $unite, $vendredi, $vendredi, 1))->toThrow(ReservationImpossible::class, 'La date de départ doit être après la date d\'arrivée.');
    expect(fn () => service()->garderDates($client, $unite, now()->subDays(2), now()->addDay(), 1))->toThrow(ReservationImpossible::class, 'dans le passé');
    expect(fn () => service()->garderDates($client, $unite, $vendredi, $vendredi->addDay(), 3))->toThrow(ReservationImpossible::class, 'de 1 à 2 personnes');
    $enAttente = creerLogement(15000, 20000, ['statut_verification' => 'en_attente']);
    expect(fn () => service()->garderDates($client, $enAttente, $vendredi, $vendredi->addDay(), 1))->toThrow(ReservationImpossible::class, 'pas disponible');
});

it('seul un client peut réserver en ligne', function () {
    $vendredi = prochainVendredi();

    expect(fn () => service()->garderDates(creerUtilisateur(Role::Gerant), creerLogement(), $vendredi, $vendredi->addDay(), 1))->toThrow(AuthorizationException::class);
});

it('une réservation « En attente » expire et libère les dates après la durée configurée', function () {
    Parametres::definir('reservation.duree_blocage_minutes', '1'); // durée très courte
    $unite = creerLogement();
    $vendredi = prochainVendredi();
    $reservation = service()->garderDates(creerUtilisateur(Role::Client), $unite, $vendredi, $vendredi->addDays(2), 1);

    // Avant l'échéance : rien ne bouge et les dates restent prises.
    expect(Artisan::call('gesimo:expirer-reservations'))->toBe(0);
    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);
    expect(fn () => service()->garderDates(creerUtilisateur(Role::Client), $unite, $vendredi, $vendredi->addDays(2), 1))->toThrow(ReservationImpossible::class);

    $this->travel(2)->minutes(); // le délai est passé
    Artisan::call('gesimo:expirer-reservations');

    expect($reservation->fresh()->statut)->toBe(StatutReservation::Expiree);
    $nouvelle = service()->garderDates(creerUtilisateur(Role::Client), $unite, $vendredi, $vendredi->addDays(2), 1); // les dates sont libres
    expect($nouvelle->statut)->toBe(StatutReservation::EnAttente);
});

it('l\'expiration ne touche que les réservations en attente dont le délai est passé', function () {
    $unite = creerLogement();
    $vendredi = prochainVendredi();
    $confirmee = insererReservationSql($unite, $vendredi->toDateString(), $vendredi->addDay()->toDateString());
    $recente = insererReservationSql($unite, $vendredi->addDays(3)->toDateString(), $vendredi->addDays(4)->toDateString(), ['statut' => 'en_attente', 'expire_le' => now()->addHour()]);
    $perimee = insererReservationSql($unite, $vendredi->addDays(6)->toDateString(), $vendredi->addDays(7)->toDateString(), ['statut' => 'en_attente', 'expire_le' => now()->subMinute()]);

    expect(service()->expirerLesReservations())->toBe(1);
    expect(DB::table('reservation')->whereIn('id', [$confirmee, $recente, $perimee])->orderBy('id')->pluck('statut')->all())->toBe(['confirmee', 'en_attente', 'expiree']);
});

it('une réservation hors plateforme bloque le calendrier et ne génère ni paiement ni commission', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerLogement(15000, 20000, ['gerant_id' => $gerant->id]);
    $vendredi = prochainVendredi();

    $reservation = service()->creerHorsPlateforme($gerant, $unite, $vendredi, $vendredi->addDays(2), 'M. Traoré', '0511223344');

    expect($reservation->statut)->toBe(StatutReservation::HorsPlateforme)
        ->and($reservation->origine)->toBe('hors_plateforme')
        ->and([$reservation->montant_total, $reservation->acompte, $reservation->solde])->toBe([0, 0, 0])
        ->and($reservation->client_id)->toBeNull();

    // Elle bloque le calendrier…
    expect(fn () => service()->garderDates(creerUtilisateur(Role::Client), $unite, $vendredi, $vendredi->addDay(), 1))->toThrow(ReservationImpossible::class);
    // …sans aucun paiement ni écriture comptable, et la base refuse d'en ajouter.
    expect(DB::table('paiement')->count())->toBe(0)->and(DB::table('ecriture_comptable')->count())->toBe(0);
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->insert(['reservation_id' => $reservation->id, 'type' => 'commission', 'montant' => 100])))->toBe('23514');
});

it('seul le gérant de la résidence saisit une réservation par téléphone', function () {
    $unite = creerLogement();
    $vendredi = prochainVendredi();

    expect(fn () => service()->creerHorsPlateforme(creerUtilisateur(Role::Gerant), $unite, $vendredi, $vendredi->addDay(), 'X'))->toThrow(AuthorizationException::class);
    expect(fn () => service()->creerHorsPlateforme(creerUtilisateur(Role::Client), $unite, $vendredi, $vendredi->addDay(), 'X'))->toThrow(AuthorizationException::class);
});

it('exige le nom du client pour une réservation par téléphone', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerLogement(15000, 20000, ['gerant_id' => $gerant->id]);
    $vendredi = prochainVendredi();

    expect(fn () => service()->creerHorsPlateforme($gerant, $unite, $vendredi, $vendredi->addDay(), '  '))->toThrow(ReservationImpossible::class, 'Le nom du client est obligatoire.');
});

it('un client annule sa réservation en attente et libère les dates ; pas celle d\'un autre', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $vendredi = prochainVendredi();
    $reservation = service()->garderDates($client, $unite, $vendredi, $vendredi->addDays(2), 1);

    expect(fn () => app(AnnulationService::class)->annuler($reservation->fresh()->load('unite.residence'), creerUtilisateur(Role::Client)))->toThrow(AuthorizationException::class);

    app(AnnulationService::class)->annuler($reservation->fresh()->load('unite.residence'), $client);
    expect($reservation->fresh()->statut)->toBe(StatutReservation::Annulee);
    expect(service()->garderDates(creerUtilisateur(Role::Client), $unite, $vendredi, $vendredi->addDays(2), 1)->statut)->toBe(StatutReservation::EnAttente);
});

it('les transitions de statut permises par l\'application sont exactement celles de la base', function () {
    $unite = creerLogement();
    $dates = fn (int $i) => [now()->addDays(100 + $i * 3)->toDateString(), now()->addDays(101 + $i * 3)->toDateString()];
    $i = 0;

    foreach ([false, true] as $horsPlateforme) {
        foreach (StatutReservation::cases() as $depart) {
            foreach (StatutReservation::cases() as $cible) {
                if ($depart === $cible) {
                    continue;
                }
                $ligne = $horsPlateforme
                    ? ['origine' => 'hors_plateforme', 'client_id' => null, 'nom_client' => 'Appel', 'montant_total' => 0, 'acompte' => 0, 'solde' => 0]
                    : [];
                // Une réservation hors plateforme ne peut avoir que ces trois statuts ; en ligne, jamais « hors plateforme ».
                if ($horsPlateforme && ! in_array($depart, [StatutReservation::HorsPlateforme, StatutReservation::Annulee, StatutReservation::Terminee], true)) {
                    continue;
                }
                if (! $horsPlateforme && $depart === StatutReservation::HorsPlateforme) {
                    continue;
                }
                if ($horsPlateforme && ! in_array($cible, [StatutReservation::HorsPlateforme, StatutReservation::Annulee, StatutReservation::Terminee], true)) {
                    // la base refuse ce statut pour une réservation hors plateforme, par une autre règle : l'application aussi (aucune transition)
                    expect($depart->peutPasserA($cible, true))->toBeFalse();

                    continue;
                }
                if (! $horsPlateforme && $cible === StatutReservation::HorsPlateforme) {
                    expect($depart->peutPasserA($cible, false))->toBeFalse();

                    continue;
                }

                [$a, $d] = $dates($i++);
                $id = insererReservationSql($unite, $a, $d, $ligne + ['statut' => $depart->value, 'expire_le' => now()->addHour()]);
                if ($cible === StatutReservation::Confirmee && ! $horsPlateforme) {
                    // « Confirmée » exige un paiement réussi : on le fournit, pour ne tester que la liste des transitions.
                    DB::table('paiement')->insert(['reservation_id' => $id, 'reference_geniuspay' => 'MATRICE-'.$id, 'montant' => 9000, 'statut' => 'en_attente', 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('paiement')->where('reservation_id', $id)->update(['statut' => 'reussi']);
                }
                $baseAccepte = codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['statut' => $cible->value])) === null;

                expect($baseAccepte)->toBe($depart->peutPasserA($cible, $horsPlateforme), "{$depart->value} → {$cible->value} (hors plateforme : ".($horsPlateforme ? 'oui' : 'non').')');
            }
        }
    }
});

it('refuse côté application un changement de statut non permis', function () {
    $unite = creerLogement();
    $id = insererReservationSql($unite, prochainVendredi()->toDateString(), prochainVendredi()->addDay()->toDateString());
    $reservation = Reservation::with('unite')->find($id);

    expect(fn () => service()->changerStatut($reservation, StatutReservation::Expiree))->toThrow(ReservationImpossible::class);
    expect(service()->changerStatut($reservation, StatutReservation::ArriveeEnregistree)->statut)->toBe(StatutReservation::ArriveeEnregistree);
});

it('un blocage de calendrier refuse les dates déjà réservées avec un message clair', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $unite = creerLogement(15000, 20000, ['gerant_id' => $gerant->id]);
    $vendredi = prochainVendredi();
    service()->creerHorsPlateforme($gerant, $unite, $vendredi->addDays(1), $vendredi->addDays(3), 'M. Traoré');

    expect(fn () => app(BlocageService::class)->bloquer($gerant, $unite, $vendredi, $vendredi->addDays(2), 'Travaux'))
        ->toThrow(ReservationImpossible::class, 'Impossible : ces dates sont déjà prises');

    $blocage = app(BlocageService::class)->bloquer($gerant, $unite, $vendredi->addDays(10), $vendredi->addDays(12), 'Travaux');
    expect($blocage->date_fin->toDateString())->toBe($vendredi->addDays(13)->toDateString()); // « Au » est inclus : libre le lendemain
    expect(fn () => service()->garderDates(creerUtilisateur(Role::Client), $unite, $vendredi->addDays(11), $vendredi->addDays(12), 1))->toThrow(ReservationImpossible::class);
});
