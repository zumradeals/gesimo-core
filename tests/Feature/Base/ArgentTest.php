<?php

use Illuminate\Support\Facades\DB;

const DOUBLON = '23505';

function insererPaiementSql(int $reservationId, string $reference = 'GP-0001', int $montant = 9000): int
{
    return DB::table('paiement')->insertGetId([
        'reservation_id' => $reservationId, 'reference_geniuspay' => $reference, 'montant' => $montant,
        'donnees_brutes' => json_encode(['evenement' => 'test']), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function reservationPourArgent(): int
{
    return insererReservationSql(creerUnite(), '2026-12-10', '2026-12-14');
}

it('refuse un paiement dont la référence est déjà enregistrée', function () {
    $reservation = reservationPourArgent();
    insererPaiementSql($reservation, 'GP-0001');

    expect(codeRefusBase(fn () => insererPaiementSql($reservation, 'GP-0001')))->toBe(DOUBLON);
    expect(codeRefusBase(fn () => insererPaiementSql(reservationPourArgent(), 'GP-0001')))->toBe(DOUBLON);
    expect(DB::table('paiement')->where('reference_geniuspay', 'like', 'GP-%')->count())->toBe(1);
});

it('accepte des paiements de références différentes', function () {
    $reservation = reservationPourArgent();

    expect(codeRefusBase(fn () => insererPaiementSql($reservation, 'GP-0001')))->toBeNull();
    expect(codeRefusBase(fn () => insererPaiementSql($reservation, 'GP-0002')))->toBeNull();
});

it('refuse un montant de paiement nul, négatif ou à virgule', function () {
    $reservation = reservationPourArgent();

    expect(codeRefusBase(fn () => insererPaiementSql($reservation, 'GP-A', 0)))->toBe('23514');
    expect(codeRefusBase(fn () => insererPaiementSql($reservation, 'GP-B', -500)))->toBe('23514');
    expect(codeRefusBase(fn () => DB::table('paiement')->insert([
        'reservation_id' => $reservation, 'reference_geniuspay' => 'GP-C', 'montant' => '1500.5', 'created_at' => now(), 'updated_at' => now(),
    ])))->not->toBeNull();
});

it('refuse un paiement qui ne vise ni une réservation ni une mise en avant', function () {
    expect(codeRefusBase(fn () => DB::table('paiement')->insert([
        'reference_geniuspay' => 'GP-ORPHELIN', 'montant' => 1000, 'created_at' => now(), 'updated_at' => now(),
    ])))->toBe('23514');
});

it('ne laisse avancer que le statut d\'un paiement, dans le bon sens', function () {
    $paiement = insererPaiementSql(reservationPourArgent());

    expect(codeRefusBase(fn () => DB::table('paiement')->where('id', $paiement)->update(['montant' => 1])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('paiement')->where('id', $paiement)->update(['reference_geniuspay' => 'AUTRE'])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('paiement')->where('id', $paiement)->update(['donnees_brutes' => json_encode(['x' => 1])])))->toBe('23000');

    expect(codeRefusBase(fn () => DB::table('paiement')->where('id', $paiement)->update(['statut' => 'reussi'])))->toBeNull();
    expect(codeRefusBase(fn () => DB::table('paiement')->where('id', $paiement)->update(['statut' => 'en_attente'])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('paiement')->where('id', $paiement)->update(['statut' => 'echoue'])))->toBe('23000');
});

it('refuse de supprimer un paiement', function () {
    $paiement = insererPaiementSql(reservationPourArgent());

    expect(codeRefusBase(fn () => DB::table('paiement')->where('id', $paiement)->delete()))->toBe('23000');
    expect(codeRefusBase(fn () => DB::statement('TRUNCATE paiement CASCADE')))->toBe('23000');
    expect(DB::table('paiement')->where('id', $paiement)->exists())->toBeTrue();
});

it('refuse de modifier ou supprimer une écriture comptable', function () {
    $reservation = reservationPourArgent();
    $ecriture = DB::table('ecriture_comptable')->insertGetId(['reservation_id' => $reservation, 'type' => 'encaissement', 'montant' => 9000]);

    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->where('id', $ecriture)->update(['montant' => 1])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->where('id', $ecriture)->update(['motif' => 'discret'])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->where('id', $ecriture)->delete()))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->delete()))->toBe('23000');
    expect(codeRefusBase(fn () => DB::statement('TRUNCATE ecriture_comptable')))->toBe('23000');

    expect(DB::table('ecriture_comptable')->where('id', $ecriture)->value('montant'))->toBe(9000);
});

it('permet de corriger par une nouvelle écriture qui renvoie vers la première', function () {
    $reservation = reservationPourArgent();
    $premiere = DB::table('ecriture_comptable')->insertGetId(['reservation_id' => $reservation, 'type' => 'commission', 'montant' => 900]);

    $code = codeRefusBase(fn () => DB::table('ecriture_comptable')->insert([
        'reservation_id' => $reservation, 'type' => 'commission', 'montant' => 100, 'correction_de' => $premiere, 'motif' => 'Erreur de taux',
    ]));

    expect($code)->toBeNull();
    expect(DB::table('ecriture_comptable')->count())->toBe(2);
});

it('refuse une écriture à montant nul, négatif, à virgule ou de type inconnu', function () {
    $reservation = reservationPourArgent();
    $ecrire = fn (array $valeurs) => DB::table('ecriture_comptable')->insert($valeurs + ['reservation_id' => $reservation, 'type' => 'commission', 'montant' => 100]);

    expect(codeRefusBase(fn () => $ecrire(['montant' => 0])))->toBe('23514');
    expect(codeRefusBase(fn () => $ecrire(['montant' => -100])))->toBe('23514');
    expect(codeRefusBase(fn () => $ecrire(['montant' => '100.5'])))->not->toBeNull();
    expect(codeRefusBase(fn () => $ecrire(['type' => 'cadeau'])))->toBe('23514');
});

it('protège les reversements et les remboursements', function () {
    $gerant = creerUtilisateur();
    $reservation = reservationPourArgent();
    $paiement = insererPaiementSql($reservation);

    $reversement = DB::table('reversement')->insertGetId([
        'gerant_id' => $gerant->id, 'reservation_id' => $reservation, 'montant' => 8000, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $remboursement = DB::table('remboursement')->insertGetId([
        'reservation_id' => $reservation, 'paiement_id' => $paiement, 'montant' => 3000, 'motif' => 'Annulation', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(codeRefusBase(fn () => DB::table('reversement')->where('id', $reversement)->update(['montant' => 1])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('reversement')->where('id', $reversement)->delete()))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('remboursement')->where('id', $remboursement)->update(['montant' => 1])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('remboursement')->where('id', $remboursement)->delete()))->toBe('23000');

    // Un reversement « payé » doit dire qui, quand, et la référence Mobile Money.
    expect(codeRefusBase(fn () => DB::table('reversement')->where('id', $reversement)->update(['statut' => 'paye'])))->toBe('23514');
    expect(codeRefusBase(fn () => DB::table('reversement')->where('id', $reversement)->update([
        'statut' => 'paye', 'paye_le' => now(), 'paye_par' => $gerant->id, 'reference_mobile_money' => 'MM-123',
    ])))->toBeNull();
    expect(codeRefusBase(fn () => DB::table('reversement')->where('id', $reversement)->update(['reference_mobile_money' => 'MM-999'])))->toBe('23000');
});
