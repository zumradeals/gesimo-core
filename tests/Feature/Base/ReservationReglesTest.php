<?php

use Illuminate\Support\Facades\DB;

const VIOLATION_CONTRAINTE = '23514';
const VIOLATION_INTEGRITE = '23000';

it('calcule le nombre de nuits dans la base', function () {
    $id = insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14');

    expect(DB::table('reservation')->where('id', $id)->value('nombre_nuits'))->toBe(4);
});

it('refuse les montants négatifs, non cohérents ou nuls pour une réservation en ligne', function (array $montants) {
    expect(codeRefusBase(fn () => insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', $montants)))->toBe(VIOLATION_CONTRAINTE);
})->with([
    'acompte négatif' => [['acompte' => -1, 'solde' => 30001]],
    'total différent de acompte + solde' => [['acompte' => 9000, 'solde' => 1000]],
    'total nul' => [['montant_total' => 0, 'acompte' => 0, 'solde' => 0]],
]);

it('refuse un montant à virgule : les montants sont des entiers', function () {
    $code = codeRefusBase(fn () => insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', ['montant_total' => '30000.5', 'acompte' => '9000.5']));

    expect($code)->not->toBeNull();
});

it('refuse une réservation en attente sans date d\'expiration', function () {
    expect(codeRefusBase(fn () => insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', ['statut' => 'en_attente', 'expire_le' => null])))->toBe(VIOLATION_CONTRAINTE);
});

it('refuse un statut inconnu', function () {
    expect(codeRefusBase(fn () => insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', ['statut' => 'inventé'])))->not->toBeNull();
});

it('RG-5 : une réservation hors plateforme n\'a ni montant ni commission', function () {
    $horsPlateforme = ['origine' => 'hors_plateforme', 'statut' => 'hors_plateforme', 'client_id' => null, 'nom_client' => 'Appel téléphonique'];

    expect(codeRefusBase(fn () => insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', $horsPlateforme + ['montant_total' => 30000, 'acompte' => 0, 'solde' => 30000])))->toBe(VIOLATION_CONTRAINTE);
    expect(codeRefusBase(fn () => insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', $horsPlateforme + ['montant_total' => 0, 'acompte' => 0, 'solde' => 0])))->toBeNull();
});

it('refuse un paiement, une écriture ou un remboursement sur une réservation hors plateforme', function () {
    $id = insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', [
        'origine' => 'hors_plateforme', 'statut' => 'hors_plateforme', 'client_id' => null,
        'nom_client' => 'Appel téléphonique', 'montant_total' => 0, 'acompte' => 0, 'solde' => 0,
    ]);

    expect(codeRefusBase(fn () => DB::table('paiement')->insert([
        'reservation_id' => $id, 'reference_geniuspay' => 'GP-HORS-1', 'montant' => 1000, 'created_at' => now(), 'updated_at' => now(),
    ])))->toBe(VIOLATION_CONTRAINTE);

    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->insert([
        'reservation_id' => $id, 'type' => 'commission', 'montant' => 100,
    ])))->toBe(VIOLATION_CONTRAINTE);
});

it('fige les montants et l\'origine d\'une réservation après sa création', function () {
    $id = insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14');

    expect(codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['montant_total' => 1, 'acompte' => 1, 'solde' => 0])))->toBe(VIOLATION_INTEGRITE);
    expect(codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['statut' => 'annulee'])))->toBeNull();
});

it('F-C7 : n\'accepte un avis qu\'après un séjour terminé, par le client du séjour', function () {
    $client = creerUtilisateur();
    $id = insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', ['client_id' => $client->id, 'statut' => 'arrivee_enregistree']);
    $avis = fn (int $clientId) => DB::table('avis')->insert([
        'reservation_id' => $id, 'client_id' => $clientId, 'note' => 5, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(codeRefusBase(fn () => $avis($client->id)))->toBe(VIOLATION_CONTRAINTE); // pas encore terminé

    DB::table('reservation')->where('id', $id)->update(['statut' => 'arrivee_enregistree']);
    DB::table('reservation')->where('id', $id)->update(['statut' => 'terminee']);

    expect(codeRefusBase(fn () => $avis(creerUtilisateur()->id)))->toBe(VIOLATION_CONTRAINTE); // autre personne
    expect(codeRefusBase(fn () => $avis($client->id)))->toBeNull();
});

it('refuse une note hors de 1 à 5', function () {
    $client = creerUtilisateur();
    $id = insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14', ['client_id' => $client->id, 'statut' => 'terminee']);

    expect(codeRefusBase(fn () => DB::table('avis')->insert([
        'reservation_id' => $id, 'client_id' => $client->id, 'note' => 6, 'created_at' => now(), 'updated_at' => now(),
    ])))->toBe(VIOLATION_CONTRAINTE);
});
