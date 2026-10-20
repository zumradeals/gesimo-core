<?php

use Illuminate\Support\Facades\DB;

const CHEVAUCHEMENT = '23P01';

it('refuse deux réservations actives qui se chevauchent, même insérées directement en SQL', function () {
    $unite = creerUnite();
    insererReservationSql($unite, '2026-12-10', '2026-12-14');

    $code = codeRefusBase(fn () => DB::insert(
        "INSERT INTO reservation (unite_id, client_id, date_arrivee, date_depart, montant_total, acompte, solde, statut, origine, expire_le, created_at, updated_at)
         VALUES (?, ?, '2026-12-12', '2026-12-16', 30000, 9000, 21000, 'en_attente', 'plateforme', now() + interval '2 hours', now(), now())",
        [$unite->id, creerUtilisateur()->id],
    ));

    expect($code)->toBe(CHEVAUCHEMENT);
});

it('refuse aussi quand une seule nuit est en commun', function () {
    $unite = creerUnite();
    insererReservationSql($unite, '2026-12-10', '2026-12-14');

    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-13', '2026-12-15')))->toBe(CHEVAUCHEMENT);
    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-08', '2026-12-11')))->toBe(CHEVAUCHEMENT);
    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-11', '2026-12-12')))->toBe(CHEVAUCHEMENT);
});

it('autorise une arrivée le jour du départ du client précédent', function () {
    $unite = creerUnite();
    insererReservationSql($unite, '2026-12-10', '2026-12-14');

    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-14', '2026-12-16')))->toBeNull();
});

it('autorise les mêmes dates sur deux unités différentes', function () {
    $residence = creerResidence();
    insererReservationSql(creerUnite($residence), '2026-12-10', '2026-12-14');

    expect(codeRefusBase(fn () => insererReservationSql(creerUnite($residence), '2026-12-10', '2026-12-14')))->toBeNull();
});

it('compte les statuts en attente, confirmée, arrivée enregistrée et hors plateforme comme occupés', function (string $statut) {
    $unite = creerUnite();
    $surcharges = ['statut' => $statut, 'expire_le' => now()->addHours(2)];
    if ($statut === 'hors_plateforme') {
        $surcharges += ['origine' => 'hors_plateforme', 'client_id' => null, 'nom_client' => 'Appel téléphonique', 'montant_total' => 0, 'acompte' => 0, 'solde' => 0];
    }
    insererReservationSql($unite, '2026-12-10', '2026-12-14', $surcharges);

    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-11', '2026-12-13')))->toBe(CHEVAUCHEMENT);
})->with(['en_attente', 'confirmee', 'arrivee_enregistree', 'hors_plateforme']);

it('libère les dates quand la réservation est expirée, annulée, terminée ou en litige', function (array $chemin) {
    $unite = creerUnite();
    $depart = array_shift($chemin);
    $id = insererReservationSql($unite, '2026-12-10', '2026-12-14', $depart === 'en_attente'
        ? ['statut' => 'en_attente', 'expire_le' => now()->addHours(2)]
        : ['statut' => $depart]);

    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-11', '2026-12-13')))->toBe(CHEVAUCHEMENT);

    foreach ($chemin as $statut) { // chaque étape est un changement de statut permis
        DB::table('reservation')->where('id', $id)->update(['statut' => $statut]);
    }

    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-11', '2026-12-13')))->toBeNull();
})->with([
    'expirée' => [['en_attente', 'expiree']],
    'annulée' => [['confirmee', 'annulee']],
    'terminée' => [['confirmee', 'arrivee_enregistree', 'terminee']],
    'en litige' => [['confirmee', 'litige']],
]);

it('refuse de déplacer une réservation sur des dates déjà prises', function () {
    $unite = creerUnite();
    insererReservationSql($unite, '2026-12-10', '2026-12-14');
    $autre = insererReservationSql($unite, '2026-12-20', '2026-12-22');

    $code = codeRefusBase(fn () => DB::table('reservation')->where('id', $autre)->update(['date_arrivee' => '2026-12-13', 'date_depart' => '2026-12-15']));

    expect($code)->toBe(CHEVAUCHEMENT);
});

it('refuse un blocage de calendrier sur des dates déjà réservées, et inversement', function () {
    $unite = creerUnite();
    insererReservationSql($unite, '2026-12-10', '2026-12-14');

    $blocage = fn (string $debut, string $fin) => DB::table('blocage_calendrier')->insert([
        'unite_id' => $unite->id, 'date_debut' => $debut, 'date_fin' => $fin, 'motif' => 'Travaux', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(codeRefusBase(fn () => $blocage('2026-12-12', '2026-12-20')))->toBe(CHEVAUCHEMENT);
    expect(codeRefusBase(fn () => $blocage('2026-12-14', '2026-12-20')))->toBeNull();
    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-16', '2026-12-18')))->toBe(CHEVAUCHEMENT);
});

it('refuse deux blocages qui se chevauchent', function () {
    $unite = creerUnite();
    $blocage = fn (string $debut, string $fin) => DB::table('blocage_calendrier')->insert([
        'unite_id' => $unite->id, 'date_debut' => $debut, 'date_fin' => $fin, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $blocage('2026-12-01', '2026-12-05');

    expect(codeRefusBase(fn () => $blocage('2026-12-04', '2026-12-08')))->toBe(CHEVAUCHEMENT);
});

it('refuse des dates incohérentes', function () {
    $unite = creerUnite();

    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-14', '2026-12-14')))->toBe('23514');
    expect(codeRefusBase(fn () => insererReservationSql($unite, '2026-12-14', '2026-12-10')))->toBe('23514');
});
