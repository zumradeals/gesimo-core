<?php

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Format;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Livewire\AnnulerReservation;
use App\Paiement\Livewire\Controle;
use App\Paiement\Models\Paiement;
use App\Paiement\Models\Remboursement;
use App\Paiement\Models\Reversement;
use App\Paiement\Services\AnnulationService;
use App\Paiement\Services\ArriveeService;
use App\Paiement\Services\Comptabilite;
use App\Paiement\Services\Rapprochement;
use App\Paiement\Services\ReversementService;
use App\Parametres\Services\Parametres;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    configurerGeniusPay();
    $this->faux = fauxGeniusPay();
});

/** Réservation payée et confirmée par le vrai circuit (paiement + message signé). */
function reservationConfirmee(TestCase $test, array $options = []): Reservation
{
    $reservation = reservationAPayer($options);
    payerEtConfirmer($test, $test->faux, $reservation);

    return $reservation->fresh()->load('unite.residence');
}

function gerantDe(Reservation $r): Utilisateur
{
    return $r->unite->residence->gerant;
}

function ecritures(string $type, ?int $reservationId = null): int
{
    return (int) DB::table('ecriture_comptable')->where('type', $type)->when($reservationId, fn ($q) => $q->where('reservation_id', $reservationId))->sum('montant');
}

function arriveeEn(Reservation $r, int $joursAvant = 0, int $heureArrivee = 14): CarbonImmutable
{
    return CarbonImmutable::parse($r->date_arrivee->toDateString())->setTime($heureArrivee, 0)->subDays($joursAvant);
}

// --- Check-in : la commission est retenue, la part du gérant devient « à reverser » ------------------------

it('à l\'arrivée : commission sur le prix du séjour, retenue sur l\'acompte, et le reste devient « à reverser » au gérant', function () {
    Parametres::definir('paiement.frais_pourcentage', '2.5'); // plancher d'acompte : 13 %
    $r = reservationConfirmee($this, ['residence' => ['mode_paiement' => 'acompte', 'pourcentage_acompte' => 30]]);
    expect([$r->montant_total, $r->acompte, $r->solde])->toBe([55000, 16500, 38500]);
    $this->travelTo(arriveeEn($r));

    app(ArriveeService::class)->enregistrerArrivee($r, gerantDe($r));

    // 10 % de 55 000 = 5 500 de commission ; part du gérant = 16 500 − 5 500 = 11 000.
    expect($r->fresh()->statut)->toBe(StatutReservation::ArriveeEnregistree)
        ->and(ecritures('commission', $r->id))->toEqual(5500)->and(ecritures('part_gerant', $r->id))->toEqual(11000);
    $reversement = Reversement::firstOrFail();
    expect($reversement->statut)->toBe('a_payer')->and($reversement->montant)->toBe(11000)->and($reversement->gerant_id)->toBe(gerantDe($r)->id)->and($reversement->reservation_id)->toBe($r->id);
    expect(DB::table('checkin')->where('reservation_id', $r->id)->exists())->toBeTrue();
});

it('paiement intégral : la commission est retenue sur le reversement', function () {
    $r = reservationConfirmee($this); // paiement total : 55 000 en ligne
    $this->travelTo(arriveeEn($r));

    app(ArriveeService::class)->enregistrerArrivee($r, gerantDe($r));

    expect(ecritures('commission', $r->id))->toEqual(5500)->and(ecritures('part_gerant', $r->id))->toEqual(49500)->and(Reversement::firstOrFail()->montant)->toBe(49500);
});

it('le taux de commission est figé à la confirmation : le changer ensuite ne touche pas cette réservation', function () {
    $r = reservationConfirmee($this);
    Parametres::definir('commission.pourcentage', '50');
    $this->travelTo(arriveeEn($r));

    app(ArriveeService::class)->enregistrerArrivee($r, gerantDe($r));

    expect(ecritures('commission', $r->id))->toEqual(5500);
});

it('arrondit la commission au franc le plus proche, sans dépasser ce qui est encaissé', function () {
    $r = new Reservation(['commission_pourcentage' => 12.5]);

    expect(Comptabilite::calculerCommission($r, 10001, 99999))->toBe(1250)   // 1 250,125
        ->and(Comptabilite::calculerCommission($r, 10004, 99999))->toBe(1251) // 1 250,5 → au-dessus
        ->and(Comptabilite::calculerCommission($r, 33333, 99999))->toBe(4167) // 4 166,625
        ->and(Comptabilite::calculerCommission($r, 100000, 5000))->toBe(5000) // plafonnée à l'encaissé
        ->and(Comptabilite::calculerCommission(new Reservation(['commission_pourcentage' => 0]), 55000, 99999))->toBe(0);
});

it('refuse l\'arrivée d\'une réservation non confirmée, avant la date, après le séjour, ou déjà enregistrée', function () {
    $service = app(ArriveeService::class);
    $nonPayee = reservationAPayer();
    $nonPayee->load('unite.residence');
    $this->travelTo(arriveeEn($nonPayee));
    expect(fn () => $service->enregistrerArrivee($nonPayee, gerantDe($nonPayee)))->toThrow(ReservationImpossible::class, 'acompte non payé');

    $this->travelBack();
    $r = reservationConfirmee($this, ['arrivee' => prochainVendredi()->addDays(28)]);
    $this->travelTo(arriveeEn($r, 2));
    expect(fn () => $service->enregistrerArrivee($r, gerantDe($r)))->toThrow(ReservationImpossible::class, 'qu\'à partir de la date d\'arrivée');

    $this->travelTo(arriveeEn($r, -3)); // le jour du départ
    expect(fn () => $service->enregistrerArrivee($r, gerantDe($r)))->toThrow(ReservationImpossible::class, 'terminé');

    $this->travelTo(arriveeEn($r, -1));
    $service->enregistrerArrivee($r, gerantDe($r));
    expect(fn () => $service->enregistrerArrivee($r->fresh(), gerantDe($r)))->toThrow(ReservationImpossible::class);
    expect(Reversement::count())->toBe(1)->and(ecritures('commission'))->toEqual(5500); // une seule fois
});

it('le départ termine le séjour ; il faut d\'abord l\'arrivée', function () {
    $r = reservationConfirmee($this);
    $service = app(ArriveeService::class);
    $this->travelTo(arriveeEn($r));
    expect(fn () => $service->enregistrerDepart($r, gerantDe($r)))->toThrow(ReservationImpossible::class, 'arrivée est enregistrée');

    $service->enregistrerArrivee($r, gerantDe($r));
    $service->enregistrerDepart($r->fresh(), gerantDe($r));

    expect($r->fresh()->statut)->toBe(StatutReservation::Terminee)->and(DB::table('checkin')->whereNotNull('depart_le')->count())->toBe(1);
});

it('seul le gérant ou le personnel autorisé enregistre l\'arrivée', function () {
    $r = reservationConfirmee($this);
    $this->travelTo(arriveeEn($r));
    $service = app(ArriveeService::class);
    $avecDroit = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $r->unite->residence_id, 'utilisateur_id' => $avecDroit->id, 'peut_gerer_checkin_checkout' => true]);
    $sansDroit = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $r->unite->residence_id, 'utilisateur_id' => $sansDroit->id, 'peut_gerer_calendrier' => true]);

    expect(fn () => $service->enregistrerArrivee($r, $sansDroit))->toThrow(AuthorizationException::class);
    expect(fn () => $service->enregistrerArrivee($r, creerUtilisateur(Role::Gerant)))->toThrow(AuthorizationException::class);
    expect(fn () => $service->enregistrerArrivee($r, $r->client))->toThrow(AuthorizationException::class);

    $service->enregistrerArrivee($r, $avecDroit);
    expect($r->fresh()->statut)->toBe(StatutReservation::ArriveeEnregistree);
});

// --- Reversement manuel -------------------------------------------------------------------------------------

function reversementAPayer(TestCase $test): Reversement
{
    $r = reservationConfirmee($test);
    $test->travelTo(arriveeEn($r));
    app(ArriveeService::class)->enregistrerArrivee($r, gerantDe($r));

    return Reversement::firstOrFail();
}

it('l\'admin enregistre un reversement : référence, date, écriture inaltérable, journal', function () {
    $reversement = reversementAPayer($this);
    $admin = creerUtilisateur(Role::Admin);

    app(ReversementService::class)->enregistrer($admin, $reversement, 'MM-48213', now());

    $reversement = $reversement->fresh();
    expect($reversement->statut)->toBe('paye')->and($reversement->reference_mobile_money)->toBe('MM-48213')->and($reversement->paye_par)->toBe($admin->id)->and($reversement->paye_le)->not->toBeNull();
    expect(ecritures('reversement'))->toEqual(49500);
    expect(DB::table('journal_audit')->where('action', 'reversement.enregistre')->where('utilisateur_id', $admin->id)->exists())->toBeTrue();
    // Une fois payé, plus rien ne se modifie, même en SQL.
    expect(codeRefusBase(fn () => DB::table('reversement')->where('id', $reversement->id)->update(['reference_mobile_money' => 'AUTRE'])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->where('type', 'reversement')->update(['montant' => 1])))->toBe('23000');
});

it('refuse un reversement sans référence, daté du futur, déjà enregistré, ou fait par un non-admin', function () {
    $reversement = reversementAPayer($this);
    $service = app(ReversementService::class);
    $admin = creerUtilisateur(Role::Admin);

    expect(fn () => $service->enregistrer(creerUtilisateur(Role::Gerant), $reversement, 'MM-1', now()))->toThrow(AuthorizationException::class);
    expect(fn () => $service->enregistrer(gerantDe(Reservation::first()->load('unite.residence')), $reversement, 'MM-1', now()))->toThrow(AuthorizationException::class);
    expect(fn () => $service->enregistrer($admin, $reversement, '   ', now()))->toThrow(PaiementImpossible::class, 'obligatoire');
    expect(fn () => $service->enregistrer($admin, $reversement, 'MM-1', now()->addDays(2)))->toThrow(PaiementImpossible::class, 'futur');

    $service->enregistrer($admin, $reversement, 'MM-1', now());
    expect(fn () => $service->enregistrer($admin, $reversement->fresh(), 'MM-2', now()))->toThrow(PaiementImpossible::class, 'déjà été enregistré');
    expect(ecritures('reversement'))->toEqual(49500); // jamais deux fois
});

it('le super-administrateur peut aussi reverser', function () {
    $reversement = reversementAPayer($this);

    app(ReversementService::class)->enregistrer(creerUtilisateur(Role::SuperAdmin), $reversement, 'MM-9', now());

    expect($reversement->fresh()->statut)->toBe('paye');
});

// --- Annulations et remboursements ----------------------------------------------------------------------------

it('annulation du client plus de 24 h avant l\'arrivée : remboursement intégral, aucune commission', function () {
    $r = reservationConfirmee($this);
    $this->travelTo(arriveeEn($r, 3));
    $client = $r->client;

    $apercu = app(AnnulationService::class)->apercu($r, $client);
    expect($apercu)->toMatchArray(['cas' => 'integral', 'paye' => 55000, 'rembourse' => 55000, 'retenu' => 0]);

    app(AnnulationService::class)->annuler($r, $client);

    expect($r->fresh()->statut)->toBe(StatutReservation::Annulee)->and($r->fresh()->annulation_motif)->toBe('client')
        ->and(Remboursement::firstOrFail())->statut->toBe('effectue')->montant->toBe(55000)
        ->and(ecritures('remboursement'))->toEqual(55000)->and(ecritures('commission'))->toEqual(0)->and(Reversement::count())->toBe(0);
    expect($this->faux->remboursements[0]['corps'])->not->toHaveKey('amount'); // remboursement total chez GeniusPay
});

it('annulation du client moins de 24 h avant l\'arrivée : une nuit retenue, le reste remboursé, la commission ne porte que sur la nuit', function () {
    $r = reservationConfirmee($this);
    $this->travelTo(arriveeEn($r, 0, 14)->subHours(10)); // 10 h avant l'arrivée
    $client = $r->client;

    $apercu = app(AnnulationService::class)->apercu($r, $client);
    expect($apercu)->toMatchArray(['cas' => 'nuit_retenue', 'paye' => 55000, 'nuit' => 20000, 'retenu' => 20000, 'rembourse' => 35000]);

    app(AnnulationService::class)->annuler($r, $client);

    // Nuit retenue 20 000 : commission 10 % de la nuit = 2 000, part du gérant 18 000 ; remboursement 35 000.
    expect(ecritures('remboursement'))->toEqual(35000)->and(ecritures('commission', $r->id))->toEqual(2000)->and(ecritures('part_gerant', $r->id))->toEqual(18000)
        ->and(Reversement::firstOrFail()->montant)->toBe(18000)->and(Remboursement::firstOrFail()->montant)->toBe(35000);
    expect($this->faux->remboursements[0]['corps']['amount'])->toBe(35000); // remboursement partiel
});

it('si l\'acompte payé est inférieur à une nuit, il est retenu en entier et rien n\'est remboursé', function () {
    Parametres::definir('paiement.frais_pourcentage', '2.5');
    $r = reservationConfirmee($this, ['residence' => ['mode_paiement' => 'acompte', 'pourcentage_acompte' => 30]]); // acompte 16 500 < nuit 20 000
    $this->travelTo(arriveeEn($r)->subHours(5));

    $apercu = app(AnnulationService::class)->apercu($r, $r->client);
    expect($apercu)->toMatchArray(['cas' => 'nuit_retenue', 'retenu' => 16500, 'rembourse' => 0]);
    app(AnnulationService::class)->annuler($r, $r->client);

    expect(Remboursement::count())->toBe(0)->and($this->faux->remboursements)->toBeEmpty()
        ->and(ecritures('commission', $r->id))->toEqual(2000)->and(ecritures('part_gerant', $r->id))->toEqual(14500);
});

it('si le gérant annule (RG-10), le client est remboursé en totalité, même tardivement, et le gérant ne touche rien', function () {
    $r = reservationConfirmee($this);
    $this->travelTo(arriveeEn($r)->subHours(2));
    $gerant = gerantDe($r);

    $apercu = app(AnnulationService::class)->apercu($r, $gerant);
    expect($apercu)->toMatchArray(['cas' => 'integral', 'rembourse' => 55000, 'retenu' => 0]);
    app(AnnulationService::class)->annuler($r, $gerant);

    expect($r->fresh()->annulation_motif)->toBe('gerant')->and(ecritures('remboursement'))->toEqual(55000)->and(Reversement::count())->toBe(0)->and(ecritures('commission'))->toEqual(0);
});

it('client absent (RG-8) : aucun remboursement, tout est acquis, la part du gérant est à reverser', function () {
    $r = reservationConfirmee($this);
    $this->travelTo(arriveeEn($r, -1)); // le lendemain de l'arrivée prévue

    app(AnnulationService::class)->declarerAbsent($r, gerantDe($r));

    expect($r->fresh()->statut)->toBe(StatutReservation::Annulee)->and($r->fresh()->annulation_motif)->toBe('absence')
        ->and(Remboursement::count())->toBe(0)->and(ecritures('commission', $r->id))->toEqual(5500)->and(ecritures('part_gerant', $r->id))->toEqual(49500)
        ->and(Reversement::firstOrFail()->montant)->toBe(49500);
});

it('« client absent » n\'est possible qu\'à partir de la date d\'arrivée, pour une réservation confirmée, par une personne autorisée', function () {
    $r = reservationConfirmee($this);
    $service = app(AnnulationService::class);

    $this->travelTo(arriveeEn($r, 2));
    expect(fn () => $service->declarerAbsent($r, gerantDe($r)))->toThrow(ReservationImpossible::class, 'à partir de la date d\'arrivée');
    $this->travelTo(arriveeEn($r));
    expect(fn () => $service->declarerAbsent($r, $r->client))->toThrow(AuthorizationException::class);
    expect(fn () => $service->declarerAbsent(reservationAPayer(['arrivee' => prochainVendredi()->addDays(40)])->load('unite.residence'), creerUtilisateur(Role::Gerant)))->toThrow(AuthorizationException::class);
    $service->declarerAbsent($r, gerantDe($r));
    expect(fn () => $service->declarerAbsent($r->fresh(), gerantDe($r)))->toThrow(ReservationImpossible::class, 'confirmée');
});

it('annulation d\'une réservation en attente : aucun argent, les dates sont libérées ; on ne peut pas annuler deux fois ni après le séjour', function () {
    $r = reservationAPayer();
    $service = app(AnnulationService::class);

    expect($service->apercu($r, $r->client))->toMatchArray(['cas' => 'aucun', 'rembourse' => 0]);
    $service->annuler($r->load('unite.residence'), $r->client);

    expect($r->fresh()->statut)->toBe(StatutReservation::Annulee)->and(Remboursement::count())->toBe(0);
    expect(fn () => $service->annuler($r->fresh()->load('unite.residence'), $r->client))->toThrow(ReservationImpossible::class);

    $c = reservationConfirmee($this, ['arrivee' => prochainVendredi()->addDays(60)]);
    $this->travelTo(arriveeEn($c, -1));
    expect(fn () => $service->annuler($c, $c->client))->toThrow(ReservationImpossible::class, 'séjour a commencé');
});

it('personne d\'autre que le client, le gérant de la résidence ou l\'admin n\'annule une réservation', function () {
    $r = reservationConfirmee($this);
    $service = app(AnnulationService::class);

    expect(fn () => $service->annuler($r, creerUtilisateur(Role::Client)))->toThrow(AuthorizationException::class);
    expect(fn () => $service->annuler($r, creerUtilisateur(Role::Gerant)))->toThrow(AuthorizationException::class);
    expect(fn () => $service->annuler($r, creerUtilisateur(Role::Personnel)))->toThrow(AuthorizationException::class);
    expect($r->fresh()->statut)->toBe(StatutReservation::Confirmee);
});

it('le délai d\'annulation gratuite et l\'heure d\'arrivée sont des réglages', function () {
    $r = reservationConfirmee($this);
    $this->travelTo(arriveeEn($r)->subHours(30)); // 30 h avant : gratuit avec 24 h
    expect(app(AnnulationService::class)->apercu($r, $r->client)['cas'])->toBe('integral');

    Parametres::definir('annulation.delai_gratuit_heures', '48'); // 48 h : 30 h avant devient tardif
    expect(app(AnnulationService::class)->apercu($r, $r->client)['cas'])->toBe('nuit_retenue');

    Parametres::definir('annulation.delai_gratuit_heures', '24');
    Parametres::definir('annulation.heure_arrivee', '23:00'); // arrivée supposée à 23 h : 30 h avant 14 h = 39 h avant 23 h
    expect(app(AnnulationService::class)->apercu($r, $r->client)['cas'])->toBe('integral');
});

it('l\'écran d\'annulation montre le montant remboursé AVANT la confirmation', function () {
    $r = reservationConfirmee($this);
    $this->travelTo(arriveeEn($r)->subHours(10));

    Livewire::actingAs($r->client)->test(AnnulerReservation::class, ['reservation' => $r->id])
        ->assertSee('Moins de 24 h avant')->assertSee('Remboursé : '.Format::fcfa(35000))->assertSee(Format::fcfa(20000))
        ->call('confirmer')->assertRedirect(route('mes-reservations'));

    expect(ecritures('remboursement'))->toEqual(35000);
});

// --- Cohérence comptable ----------------------------------------------------------------------------------------

it('pour 10 réservations de test, part du gérant + commission + remboursements = argent encaissé, au franc près', function () {
    $service = app(AnnulationService::class);
    $arrivee = fn (int $i) => prochainVendredi()->addDays(7 * $i);
    Parametres::definir('commission.pourcentage', '12.5'); // taux qui donne des arrondis
    Parametres::definir('paiement.frais_pourcentage', '2.5');
    $acompte = ['mode_paiement' => 'acompte', 'pourcentage_acompte' => 33];

    $cas = [
        ['paiement total, arrivée', [], 3, 'arrivee'], ['acompte, arrivée', $acompte, 2, 'arrivee'], ['paiement total, arrivée', [], 5, 'arrivee'],
        ['annulation gratuite', [], 3, 'integral'], ['annulation gratuite (acompte)', $acompte, 4, 'integral'],
        ['annulation tardive', [], 3, 'tardive'], ['annulation tardive (acompte)', $acompte, 2, 'tardive'],
        ['client absent', [], 2, 'absent'], ['annulation par le gérant', $acompte, 3, 'gerant'], ['encore à venir', [], 3, 'attente'],
    ];

    $paye = 0;
    foreach ($cas as $i => [, $residence, $nuits, $issue]) {
        $this->travelBack();
        $r = reservationConfirmee($this, ['residence' => $residence, 'nuits' => $nuits, 'arrivee' => $arrivee($i), 'unite' => creerLogement(14999 + $i * 111, 19999 + $i * 37, $residence)]);
        $paye += (int) DB::table('paiement')->where('reservation_id', $r->id)->where('statut', 'reussi')->sum('montant');

        match ($issue) {
            'arrivee' => (function () use ($r) {
                $this->travelTo(arriveeEn($r));
                app(ArriveeService::class)->enregistrerArrivee($r, gerantDe($r));
            })(),
            'integral' => (function () use ($r, $service) {
                $this->travelTo(arriveeEn($r, 4));
                $service->annuler($r, $r->client);
            })(),
            'tardive' => (function () use ($r, $service) {
                $this->travelTo(arriveeEn($r)->subHours(6));
                $service->annuler($r, $r->client);
            })(),
            'absent' => (function () use ($r, $service) {
                $this->travelTo(arriveeEn($r, -1));
                $service->declarerAbsent($r, gerantDe($r));
            })(),
            'gerant' => (function () use ($r, $service) {
                $this->travelTo(arriveeEn($r)->subHours(3));
                $service->annuler($r, gerantDe($r));
            })(),
            default => null,
        };
    }
    $this->travelBack();

    $encaisse = (int) DB::table('paiement')->where('statut', 'reussi')->sum('montant');
    $rembourse = ecritures('remboursement');
    $commissions = ecritures('commission');
    $parts = ecritures('part_gerant');
    $encoreDetenu = (int) DB::table('paiement')->join('reservation', 'reservation.id', '=', 'paiement.reservation_id')
        ->where('paiement.statut', 'reussi')->where('reservation.statut', 'confirmee')->sum('paiement.montant'); // la 10e réservation, pas encore arrivée

    expect($encaisse)->toBe($paye)->and(ecritures('encaissement'))->toEqual($encaisse)
        ->and($parts + $commissions + $rembourse + $encoreDetenu)->toEqual($encaisse); // au franc près

    // Chaque réservation prise séparément est juste, elle aussi.
    foreach (Reservation::all() as $r) {
        $payeR = (int) DB::table('paiement')->where('reservation_id', $r->id)->where('statut', 'reussi')->sum('montant');
        $partageR = ecritures('commission', $r->id) + ecritures('part_gerant', $r->id) + ecritures('remboursement', $r->id);
        expect($partageR)->toEqual($r->statut === StatutReservation::Confirmee ? 0 : $payeR);
    }

    $bilan = app(Rapprochement::class)->calculer();
    expect($bilan['anomalies'])->toBeEmpty()->and($bilan['detenus'])->toBe($encoreDetenu)->and($bilan['totaux']['encaisse'])->toBe($encaisse);
});

it('le rapprochement signale tout écart entre paiements et écritures, et l\'admin corrige par une nouvelle écriture', function () {
    $r = reservationConfirmee($this);
    expect(app(Rapprochement::class)->calculer()['anomalies'])->toBeEmpty();

    // Un paiement réussi dont l'écriture manque (ex. panne entre les deux étapes).
    DB::table('paiement')->insert(['reservation_id' => $r->id, 'reference_geniuspay' => 'MTX-ORPHELIN', 'montant' => 4000, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('paiement')->where('reference_geniuspay', 'MTX-ORPHELIN')->update(['statut' => 'reussi']);

    $bilan = app(Rapprochement::class)->calculer();
    expect($bilan['anomalies']->pluck('type')->all())->toContain('encaissement_manquant', 'total_different');
    $manquante = $bilan['anomalies']->firstWhere('type', 'encaissement_manquant');
    expect($manquante['message'])->toContain('MTX-ORPHELIN')->and($manquante['action'])->toBe('ecrire_encaissement');

    $admin = creerUtilisateur(Role::Admin);
    Livewire::actingAs($admin)->test(Controle::class)->assertSee('MTX-ORPHELIN')->call('ecrireEncaissement', $manquante['id'])->assertSee('Écriture d\'encaissement créée.');

    expect(app(Rapprochement::class)->calculer()['anomalies'])->toBeEmpty();
    expect(DB::table('ecriture_comptable')->where('paiement_id', $manquante['id'])->count())->toBe(1);
    Livewire::actingAs($admin)->test(Controle::class)->call('ecrireEncaissement', $manquante['id']);
    expect(DB::table('ecriture_comptable')->where('paiement_id', $manquante['id'])->count())->toBe(1); // jamais deux fois
});

it('le rapprochement signale une commission écrite trop tôt et un partage incomplet', function () {
    $r = reservationConfirmee($this);
    DB::table('ecriture_comptable')->insert(['reservation_id' => $r->id, 'type' => 'commission', 'montant' => 100, 'created_at' => now()]);

    expect(app(Rapprochement::class)->calculer()['anomalies']->pluck('type')->all())->toContain('acquis_trop_tot');

    $this->travelTo(arriveeEn($r)->setTime(9, 0));
    DB::statement("UPDATE reservation SET statut = 'arrivee_enregistree' WHERE id = ?", [$r->id]);
    expect(app(Rapprochement::class)->calculer()['anomalies']->pluck('type')->all())->toContain('partage_incomplet');
});

it('un remboursement effectué sans écriture est signalé et corrigeable', function () {
    $r = reservationConfirmee($this);
    $paiement = Paiement::firstOrFail();
    $id = DB::table('remboursement')->insertGetId(['reservation_id' => $r->id, 'paiement_id' => $paiement->id, 'montant' => 55000, 'motif' => 'Test', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('remboursement')->where('id', $id)->update(['statut' => 'effectue']);

    $anomalie = app(Rapprochement::class)->calculer()['anomalies']->firstWhere('type', 'remboursement_sans_ecriture');
    expect($anomalie)->not->toBeNull();

    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(Controle::class)->call('ecrireRemboursement', $id);
    expect(app(Rapprochement::class)->calculer()['anomalies']->pluck('type')->all())->not->toContain('remboursement_sans_ecriture');
});

it('la base interdit d\'écrire deux fois la même écriture, même en SQL', function () {
    $r = reservationConfirmee($this);
    $paiement = Paiement::firstOrFail();

    // L'encaissement existe déjà pour ce paiement.
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->insert(['reservation_id' => $r->id, 'paiement_id' => $paiement->id, 'type' => 'encaissement', 'montant' => 55000])))->toBe('23505');

    DB::table('ecriture_comptable')->insert(['reservation_id' => $r->id, 'type' => 'commission', 'montant' => 5500]);
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->insert(['reservation_id' => $r->id, 'type' => 'commission', 'montant' => 5500])))->toBe('23505');
    DB::table('ecriture_comptable')->insert(['reservation_id' => $r->id, 'type' => 'part_gerant', 'montant' => 49500]);
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->insert(['reservation_id' => $r->id, 'type' => 'part_gerant', 'montant' => 1])))->toBe('23505');

    // Un seul reversement et un seul remboursement par réservation / paiement.
    $gerant = gerantDe($r);
    DB::table('reversement')->insert(['gerant_id' => $gerant->id, 'reservation_id' => $r->id, 'montant' => 49500, 'created_at' => now(), 'updated_at' => now()]);
    expect(codeRefusBase(fn () => DB::table('reversement')->insert(['gerant_id' => $gerant->id, 'reservation_id' => $r->id, 'montant' => 1, 'created_at' => now(), 'updated_at' => now()])))->toBe('23505');
});

it('les écritures du Lot 3 sont inaltérables : ni modification, ni suppression', function () {
    $r = reservationConfirmee($this);
    $id = DB::table('ecriture_comptable')->where('reservation_id', $r->id)->value('id');

    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->where('id', $id)->update(['montant' => 1])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('ecriture_comptable')->where('id', $id)->delete()))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('paiement')->where('reservation_id', $r->id)->update(['frais' => 0])))->toBe('23000');
    expect(codeRefusBase(fn () => DB::table('paiement')->where('reservation_id', $r->id)->delete()))->toBe('23000');
});
