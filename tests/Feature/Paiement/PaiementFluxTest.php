<?php

use App\Noyau\Enums\Role;
use App\Noyau\Services\Format;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Livewire\PagePaiement;
use App\Paiement\Models\AlerteAdmin;
use App\Paiement\Models\EvenementPaiement;
use App\Paiement\Models\Paiement;
use App\Paiement\Models\Remboursement;
use App\Paiement\Services\AnnulationService;
use App\Paiement\Services\PaiementService;
use App\Paiement\Services\RemboursementService;
use App\Parametres\Services\Parametres;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\ReservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(fn () => configurerGeniusPay());

function paiements(): PaiementService
{
    return app(PaiementService::class);
}

// --- Démarrer un paiement -------------------------------------------------------------------------------

it('démarre un paiement : crée le paiement chez GeniusPay et enregistre sa référence, ses frais et le lien', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer(); // 3 nuits ven-sam-dim : 55 000 FCFA, tout payé en ligne

    $paiement = paiements()->demarrer($reservation, $reservation->client);

    expect($paiement->statut)->toBe('en_attente')->and($paiement->montant)->toBe(55000)->and($paiement->frais)->toBe(1650)
        ->and($paiement->reference_geniuspay)->toBe('SANDBOX-0001')->and($paiement->mode)->toBe('sandbox')
        ->and($paiement->checkout_url)->toBe('https://geniuspay.ci/checkout/SANDBOX-0001')->and($paiement->lien_expire_le)->not->toBeNull()
        ->and($paiement->reference_externe)->toBe("gesimo-resa-{$reservation->id}-1");
    expect($faux->creations[0]['corps']['success_url'])->toBe(route('paiement.retour', $reservation));
    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente); // le départ chez GeniusPay ne confirme rien
});

it('réutilise le lien de paiement encore valable : jamais deux paiements ouverts pour la même réservation', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();

    $premier = paiements()->demarrer($reservation, $reservation->client);
    $second = paiements()->demarrer($reservation, $reservation->client);

    expect($second->id)->toBe($premier->id)->and($faux->creations)->toHaveCount(1)->and(Paiement::count())->toBe(1);
});

it('un nouvel essai après un échec crée un nouveau paiement avec une nouvelle référence externe', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $premier = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$premier->reference_geniuspay] = ['status' => 'failed', 'amount' => 55000];

    recevoirWebhook($this, 'payment.failed', $premier->reference_geniuspay, 55000)->assertOk();
    expect($premier->fresh()->statut)->toBe('echoue')->and($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);

    $second = paiements()->demarrer($reservation->fresh(), $reservation->client);

    expect($second->id)->not->toBe($premier->id)->and($second->reference_externe)->toBe("gesimo-resa-{$reservation->id}-2");
});

it('refuse de démarrer : paiement non configuré, commission non fixée, délai terminé, mauvais client, montant trop faible', function () {
    fauxGeniusPay();
    $reservation = reservationAPayer();
    $client = $reservation->client;

    // Un autre client ne peut pas payer à la place.
    expect(fn () => paiements()->demarrer($reservation, creerUtilisateur(Role::Client)))->toThrow(AuthorizationException::class);

    // Commission non fixée : le paiement en ligne n'est pas ouvert.
    DB::table('parametre')->where('cle', 'commission.pourcentage')->update(['valeur' => null]);
    expect(paiements()->disponible())->toBeFalse();
    expect(fn () => paiements()->demarrer($reservation, $client))->toThrow(PaiementImpossible::class, 'pas encore disponible');
    Parametres::definir('commission.pourcentage', '0'); // 0 % est une valeur valide
    expect(paiements()->disponible())->toBeTrue();

    // Clés absentes.
    DB::table('parametre')->where('cle', 'geniuspay.sandbox.cle_secrete')->update(['valeur' => null]);
    expect(paiements()->disponible())->toBeFalse();
    configurerGeniusPay();

    // Délai de garde terminé.
    $this->travel(3)->hours();
    expect(fn () => paiements()->demarrer($reservation->fresh(), $client))->toThrow(PaiementImpossible::class, 'délai de garde est terminé');
});

it('refuse un montant à payer inférieur au minimum de GeniusPay (200 FCFA)', function () {
    fauxGeniusPay();
    $client = creerUtilisateur(Role::Client);
    $id = insererReservationSql(creerLogement(), prochainVendredi()->toDateString(), prochainVendredi()->addDay()->toDateString(), ['client_id' => $client->id, 'statut' => 'en_attente', 'montant_total' => 150, 'acompte' => 150, 'solde' => 0]);

    expect(fn () => paiements()->demarrer(Reservation::findOrFail($id), $client))->toThrow(PaiementImpossible::class, 'trop faible');
});

// --- Confirmation : seulement par le serveur -------------------------------------------------------------

it('confirme la réservation quand GeniusPay envoie un message signé ET le confirme directement', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000];

    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'confirmee']);

    $reservation = $reservation->fresh();
    expect($paiement->fresh()->statut)->toBe('reussi')
        ->and($reservation->statut)->toBe(StatutReservation::Confirmee)->and($reservation->expire_le)->toBeNull()
        ->and((float) $reservation->commission_pourcentage)->toBe(10.0)
        ->and(DB::table('ecriture_comptable')->where('type', 'encaissement')->where('paiement_id', $paiement->id)->sum('montant'))->toEqual(55000);
    expect($faux->lectures)->toContain($paiement->reference_geniuspay); // vérification directe auprès du fournisseur
});

it('un message reçu deux fois (ou trois) ne produit qu\'un seul effet', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000];

    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'confirmee']);
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'doublon']);
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'doublon']);

    expect(DB::table('ecriture_comptable')->where('type', 'encaissement')->count())->toBe(1)
        ->and(Remboursement::count())->toBe(0)->and(AlerteAdmin::count())->toBe(0)
        ->and(EvenementPaiement::where('signature_valide', true)->count())->toBe(1)
        ->and($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
});

it('un message avec une fausse signature est refusé (401) : aucun effet, trace conservée', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000];

    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000, ['signature' => str_repeat('0', 64)])->assertUnauthorized();
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000, ['secret' => 'whsec_PIRATE'])->assertUnauthorized();

    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente)->and($paiement->fresh()->statut)->toBe('en_attente')
        ->and(DB::table('ecriture_comptable')->count())->toBe(0)
        ->and(EvenementPaiement::where('signature_valide', false)->count())->toBe(2)
        ->and(EvenementPaiement::where('signature_valide', false)->whereNotNull('corps')->count())->toBe(0); // le contenu falsifié n'est pas gardé
    expect($faux->lectures)->toBeEmpty(); // on n'interroge même pas le fournisseur sur un message falsifié
});

it('refuse un message sans signature et un message « test » alors que le site est en mode réel', function () {
    fauxGeniusPay();
    $this->call('POST', '/webhooks/geniuspay', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"event":"payment.success"}')->assertUnauthorized();

    configurerGeniusPay('live');
    recevoirWebhook($this, 'payment.success', 'SANDBOX-0001', 55000, ['environnement' => 'sandbox'])->assertUnauthorized();
});

it('si GeniusPay ne confirme pas encore (ou ne répond pas), le message est refusé temporairement (503) pour être renvoyé', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'pending', 'amount' => 55000]; // le message dit « réussi », le fournisseur dit « en attente »

    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertStatus(503);
    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente)->and($paiement->fresh()->statut)->toBe('en_attente')
        ->and(EvenementPaiement::where('signature_valide', true)->count())->toBe(0); // pas de trace « traité » : le renvoi sera bien traité

    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000]; // GeniusPay renvoie le message plus tard
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'confirmee']);
    expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);

    // Fournisseur injoignable : même réponse, aucun effet.
    $reservation2 = reservationAPayer(['arrivee' => prochainVendredi()->addDays(30)]);
    $paiement2 = paiements()->demarrer($reservation2, $reservation2->client);
    Http::swap(new Factory);
    Http::fake(fn () => throw new ConnectionException('réseau'));
    recevoirWebhook($this, 'payment.success', $paiement2->reference_geniuspay, 55000)->assertStatus(503);
    expect($reservation2->fresh()->statut)->toBe(StatutReservation::EnAttente);
});

it('un montant différent de celui attendu ne confirme rien et alerte l\'admin', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 1000];

    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 1000)->assertOk()->assertJson(['result' => 'montant_different']);

    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente)->and($paiement->fresh()->statut)->toBe('en_attente')
        ->and(AlerteAdmin::where('type', 'montant_different')->count())->toBe(1);
});

it('un paiement échoué ou annulé ne confirme rien', function (string $evenement) {
    fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);

    recevoirWebhook($this, $evenement, $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'echoue']);

    expect($paiement->fresh()->statut)->toBe('echoue')->and($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente)
        ->and(DB::table('ecriture_comptable')->count())->toBe(0);
})->with(['payment.failed', 'payment.cancelled']);

it('un paiement abandonné (jamais terminé) laisse la réservation expirer, sans rien débiter ni écrire', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'pending', 'amount' => 55000];

    $this->travel(3)->hours();
    Artisan::call('gesimo:expirer-reservations');

    expect($reservation->fresh()->statut)->toBe(StatutReservation::Expiree)->and($paiement->fresh()->statut)->toBe('en_attente')
        ->and(DB::table('ecriture_comptable')->count())->toBe(0);
});

it('un message pour un paiement inconnu est conservé et signalé à l\'admin, sans aucun effet', function () {
    fauxGeniusPay();

    recevoirWebhook($this, 'payment.success', 'MTX-INCONNU1', 5000)->assertOk()->assertJson(['result' => 'paiement_inconnu']);

    expect(AlerteAdmin::where('type', 'paiement_inconnu')->count())->toBe(1)->and(DB::table('ecriture_comptable')->count())->toBe(0);
});

it('les messages d\'information (paiement initié) et inconnus sont ignorés sans effet', function () {
    fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);

    recevoirWebhook($this, 'payment.initiated', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'ignore']);
    recevoirWebhook($this, 'autre.chose', $paiement->reference_geniuspay, 55000)->assertOk();

    expect($paiement->fresh()->statut)->toBe('en_attente')->and($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);
});

it('la route de réception n\'exige pas de jeton de formulaire mais refuse tout corps trop gros', function () {
    fauxGeniusPay();

    $this->call('POST', '/webhooks/geniuspay', [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('x', 70000))->assertStatus(413);
});

// --- Le retour du navigateur ne confirme jamais ----------------------------------------------------------

it('le retour du navigateur seul ne confirme rien : le serveur interroge GeniusPay', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'pending', 'amount' => 55000];

    // Même une adresse de retour « truquée » (?status=success) ne change rien : on ne lit rien du navigateur.
    $this->actingAs($reservation->client)->get(route('paiement.retour', $reservation).'?status=success&reference='.$paiement->reference_geniuspay)->assertOk()->assertSee('Vérification en cours');
    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente)->and($paiement->fresh()->statut)->toBe('en_attente');
});

it('au retour, si GeniusPay confirme le paiement, le serveur confirme la réservation et affiche la confirmation', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000];

    $this->actingAs($reservation->client)->get(route('paiement.retour', $reservation))->assertRedirect(route('reservation.confirmation', $reservation));

    expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee)->and(DB::table('ecriture_comptable')->where('type', 'encaissement')->count())->toBe(1);
    $this->actingAs($reservation->client)->get(route('reservation.confirmation', $reservation))->assertOk()->assertSee('Votre réservation est confirmée');
});

it('le retour affiche « non abouti » après un échec, et permet de réessayer tant que les dates sont gardées', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'failed', 'amount' => 55000];

    $this->actingAs($reservation->client)->get(route('paiement.retour', $reservation))->assertOk()
        ->assertSee('Paiement non abouti')->assertSee('pas été débité')->assertSee('Réessayer le paiement');
    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);
});

it('la page de confirmation est fermée tant que le paiement n\'est pas vérifié', function () {
    fauxGeniusPay();
    $reservation = reservationAPayer();

    $this->actingAs($reservation->client)->get(route('reservation.confirmation', $reservation))->assertNotFound();
});

it('le bouton « Payer » envoie le client sur la page de paiement GeniusPay, et explique les échecs simplement', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();

    Livewire::actingAs($reservation->client)->test(PagePaiement::class, ['reservation' => $reservation->id])
        ->assertSee('MODE TEST')->assertSee('Payer '.Format::fcfa(55000))->call('payer')->assertRedirect('https://geniuspay.ci/checkout/SANDBOX-0001');

    Http::swap(new Factory);
    Http::fake(['*' => Http::response('panne', 500)]);
    $autre = reservationAPayer(['arrivee' => prochainVendredi()->addDays(30)]);
    Livewire::actingAs($autre->client)->test(PagePaiement::class, ['reservation' => $autre->id])
        ->call('payer')->assertSee('été débité');
});

it('une réservation dont le délai est terminé affiche l\'erreur claire, sans appeler GeniusPay', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $this->travel(3)->hours();

    Livewire::actingAs($reservation->client)->test(PagePaiement::class, ['reservation' => $reservation->id])
        ->assertSee('Le délai de garde est terminé')->call('payer')->assertSee('Le délai de garde est terminé');
    expect($faux->creations)->toBeEmpty();
});

it('le mode réel n\'affiche pas le bandeau « mode test »', function () {
    configurerGeniusPay('live');
    fauxGeniusPay();
    $reservation = reservationAPayer();

    Livewire::actingAs($reservation->client)->test(PagePaiement::class, ['reservation' => $reservation->id])->assertDontSee('MODE TEST');
});

// --- La base elle-même refuse une confirmation sans paiement ----------------------------------------------

it('la base refuse de confirmer une réservation sans paiement réussi, même en SQL direct', function () {
    $unite = creerLogement();
    $id = DB::table('reservation')->insertGetId([
        'unite_id' => $unite->id, 'client_id' => creerUtilisateur(Role::Client)->id, 'date_arrivee' => '2026-12-10', 'date_depart' => '2026-12-12',
        'montant_total' => 30000, 'acompte' => 9000, 'solde' => 21000, 'statut' => 'en_attente', 'origine' => 'plateforme',
        'expire_le' => now()->addHours(2), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['statut' => 'confirmee'])))->toBe('23000');

    // Un paiement en attente, échoué ou trop faible ne suffit pas non plus.
    DB::table('paiement')->insert(['reservation_id' => $id, 'reference_geniuspay' => 'T-1', 'montant' => 9000, 'created_at' => now(), 'updated_at' => now()]);
    expect(codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['statut' => 'confirmee'])))->toBe('23000');
    DB::table('paiement')->where('reference_geniuspay', 'T-1')->update(['statut' => 'echoue']);
    expect(codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['statut' => 'confirmee'])))->toBe('23000');
    DB::table('paiement')->insert(['reservation_id' => $id, 'reference_geniuspay' => 'T-2', 'montant' => 5000, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('paiement')->where('reference_geniuspay', 'T-2')->update(['statut' => 'reussi']);
    expect(codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['statut' => 'confirmee'])))->toBe('23000'); // 5 000 < acompte 9 000

    DB::table('paiement')->insert(['reservation_id' => $id, 'reference_geniuspay' => 'T-3', 'montant' => 4000, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('paiement')->where('reference_geniuspay', 'T-3')->update(['statut' => 'reussi']);
    expect(codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['statut' => 'confirmee'])))->toBeNull(); // 5 000 + 4 000 couvrent l'acompte
});

it('la base refuse qu\'une réservation en ligne soit créée déjà confirmée', function () {
    $unite = creerLogement();

    $code = codeRefusBase(fn () => DB::table('reservation')->insert([
        'unite_id' => $unite->id, 'client_id' => creerUtilisateur(Role::Client)->id, 'date_arrivee' => '2026-12-10', 'date_depart' => '2026-12-12',
        'montant_total' => 30000, 'acompte' => 9000, 'solde' => 21000, 'statut' => 'confirmee', 'origine' => 'plateforme', 'created_at' => now(), 'updated_at' => now(),
    ]));

    expect($code)->toBe('23000');
});

it('aucune confirmation n\'est possible par l\'application hors paiement vérifié', function () {
    fauxGeniusPay();
    $reservation = reservationAPayer();

    expect(fn () => app(ReservationService::class)->changerStatut($reservation, StatutReservation::Confirmee))->toThrow(QueryException::class);
    expect($reservation->fresh()->statut)->toBe(StatutReservation::EnAttente);
});

// --- Paiements tardifs, doubles, après annulation ----------------------------------------------------------

it('paiement tardif : les dates sont encore libres → la réservation est confirmée et l\'admin est prévenu', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $this->travel(3)->hours();
    Artisan::call('gesimo:expirer-reservations');
    expect($reservation->fresh()->statut)->toBe(StatutReservation::Expiree);

    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000];
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'confirmee_tardive']);

    expect($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee)->and(Remboursement::count())->toBe(0)
        ->and(AlerteAdmin::where('type', 'paiement_tardif_reattribue')->count())->toBe(1);
});

it('paiement tardif : les dates ont été reprises → remboursement automatique et l\'admin est prévenu', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    $this->travel(3)->hours();
    Artisan::call('gesimo:expirer-reservations');
    // Quelqu'un d'autre prend les mêmes dates entre-temps.
    $autre = app(ReservationService::class)->garderDates(creerUtilisateur(Role::Client), $reservation->unite, $reservation->date_arrivee, $reservation->date_depart, 1);

    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000];
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'rembourse_tardif']);

    expect($reservation->fresh()->statut)->toBe(StatutReservation::Expiree)->and($autre->fresh()->statut)->toBe(StatutReservation::EnAttente);
    $remboursement = Remboursement::firstOrFail();
    expect($remboursement->statut)->toBe('effectue')->and($remboursement->montant)->toBe(55000)->and($remboursement->reference_fournisseur)->toBe('TXN-R1')
        ->and($faux->remboursements)->toHaveCount(1)->and($faux->remboursements[0]['reference'])->toBe($paiement->reference_geniuspay)
        ->and($faux->remboursements[0]['corps'])->not->toHaveKey('amount') // remboursement total
        ->and(AlerteAdmin::where('type', 'paiement_tardif_rembourse')->count())->toBe(1)
        ->and(DB::table('ecriture_comptable')->where('type', 'remboursement')->sum('montant'))->toEqual(55000)
        ->and(DB::table('ecriture_comptable')->where('type', 'encaissement')->sum('montant'))->toEqual(55000);

    // Le client voit le message clair au retour.
    $this->actingAs($reservation->client)->get(route('paiement.retour', $reservation))->assertSee('Paiement remboursé');
});

it('deux paiements pour la même réservation : le second est remboursé (double paiement)', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $premier = paiements()->demarrer($reservation, $reservation->client);
    $faux->etats[$premier->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000];
    recevoirWebhook($this, 'payment.success', $premier->reference_geniuspay, 55000)->assertOk();

    // Le client a ouvert un second paiement avant la confirmation du premier (deux onglets).
    $second = Paiement::create(['reservation_id' => $reservation->id, 'reference_geniuspay' => 'SANDBOX-9999', 'montant' => 55000, 'mode' => 'sandbox']);
    $faux->etats['SANDBOX-9999'] = ['status' => 'completed', 'amount' => 55000];
    recevoirWebhook($this, 'payment.success', 'SANDBOX-9999', 55000)->assertOk()->assertJson(['result' => 'rembourse_doublon']);

    expect(Remboursement::where('paiement_id', $second->id)->value('statut'))->toBe('effectue')
        ->and(Remboursement::where('paiement_id', $premier->id)->exists())->toBeFalse()
        ->and(AlerteAdmin::where('type', 'double_paiement')->count())->toBe(1)
        ->and($reservation->fresh()->statut)->toBe(StatutReservation::Confirmee);
});

it('un paiement qui arrive pour une réservation déjà annulée est remboursé', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = paiements()->demarrer($reservation, $reservation->client);
    app(AnnulationService::class)->annuler($reservation->fresh()->load('unite.residence'), $reservation->client);

    $faux->etats[$paiement->reference_geniuspay] = ['status' => 'completed', 'amount' => 55000];
    recevoirWebhook($this, 'payment.success', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'rembourse_annulee']);

    expect($reservation->fresh()->statut)->toBe(StatutReservation::Annulee)->and(Remboursement::firstOrFail()->statut)->toBe('effectue')
        ->and(AlerteAdmin::where('type', 'paiement_apres_annulation')->count())->toBe(1);
});

// --- Remboursements : échec, relance, fait à la main ------------------------------------------------------

it('si GeniusPay ne peut pas rembourser, la demande reste « à traiter » ; l\'admin relance puis ça passe', function () {
    $faux = fauxGeniusPay([], remboursementPossible: false);
    $reservation = reservationAPayer();
    $paiement = payerEtConfirmer($this, $faux, $reservation);

    app(AnnulationService::class)->annuler($reservation->fresh()->load('unite.residence'), $reservation->client);

    $remboursement = Remboursement::firstOrFail();
    expect($remboursement->statut)->toBe('en_attente')->and($remboursement->tentatives)->toBe(1)->and($remboursement->derniere_erreur)->not->toBeNull()
        ->and(AlerteAdmin::where('type', 'remboursement_en_echec')->whereNull('traitee_le')->count())->toBe(1)
        ->and(DB::table('ecriture_comptable')->where('type', 'remboursement')->count())->toBe(0); // rien n'est écrit tant que l'argent n'est pas rendu

    $faux->remboursementPossible = true;
    $admin = creerUtilisateur(Role::Admin);
    app(RemboursementService::class)->relancer($remboursement, $admin);

    expect($remboursement->fresh()->statut)->toBe('effectue')->and(DB::table('ecriture_comptable')->where('type', 'remboursement')->sum('montant'))->toEqual(55000)
        ->and(AlerteAdmin::where('type', 'remboursement_en_echec')->whereNull('traitee_le')->count())->toBe(0);
});

it('l\'admin peut enregistrer un remboursement fait à la main ; un non-admin ne le peut pas', function () {
    $faux = fauxGeniusPay([], remboursementPossible: false);
    $reservation = reservationAPayer();
    payerEtConfirmer($this, $faux, $reservation);
    app(AnnulationService::class)->annuler($reservation->fresh()->load('unite.residence'), $reservation->client);
    $remboursement = Remboursement::firstOrFail();
    $service = app(RemboursementService::class);

    expect(fn () => $service->marquerFaitMain($remboursement, creerUtilisateur(Role::Gerant), 'MM-1'))->toThrow(AuthorizationException::class);
    expect(fn () => $service->marquerFaitMain($remboursement, creerUtilisateur(Role::Admin), '  '))->toThrow(PaiementImpossible::class, 'obligatoire');

    $service->marquerFaitMain($remboursement, creerUtilisateur(Role::Admin), 'MM-77');
    expect($remboursement->fresh())->statut->toBe('effectue')->reference_fournisseur->toBe('manuel:MM-77');
    expect(DB::table('ecriture_comptable')->where('type', 'remboursement')->count())->toBe(1);
});

it('le message « remboursé » de GeniusPay constate le remboursement en attente, une seule fois', function () {
    $faux = fauxGeniusPay([], remboursementPossible: false);
    $reservation = reservationAPayer();
    $paiement = payerEtConfirmer($this, $faux, $reservation);
    app(AnnulationService::class)->annuler($reservation->fresh()->load('unite.residence'), $reservation->client);

    recevoirWebhook($this, 'payment.refunded', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'rembourse']);
    recevoirWebhook($this, 'payment.refunded', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'doublon']);

    expect(Remboursement::firstOrFail()->statut)->toBe('effectue')->and(DB::table('ecriture_comptable')->where('type', 'remboursement')->count())->toBe(1);
});

it('un remboursement fait directement chez GeniusPay, sans demande de GESIMO, est signalé', function () {
    $faux = fauxGeniusPay();
    $reservation = reservationAPayer();
    $paiement = payerEtConfirmer($this, $faux, $reservation);

    recevoirWebhook($this, 'payment.refunded', $paiement->reference_geniuspay, 55000)->assertOk()->assertJson(['result' => 'rembourse_externe']);

    expect(AlerteAdmin::where('type', 'remboursement_externe')->count())->toBe(1);
});

// --- Aucun secret nulle part ----------------------------------------------------------------------------

it('aucune clé secrète n\'apparaît dans les messages stockés, les alertes, les réponses ni les journaux', function () {
    $faux = fauxGeniusPay([], remboursementPossible: false);
    $reservation = reservationAPayer();
    $paiement = payerEtConfirmer($this, $faux, $reservation);
    app(AnnulationService::class)->annuler($reservation->fresh()->load('unite.residence'), $reservation->client);
    $reponse = recevoirWebhook($this, 'payment.success', 'MTX-X', 1, ['signature' => 'faux']);

    $toutesLesTables = '';
    foreach (['paiement', 'evenement_paiement', 'alerte_admin', 'remboursement', 'ecriture_comptable', 'journal_audit', 'parametre_historique'] as $table) {
        $toutesLesTables .= json_encode(DB::table($table)->get());
    }
    $pages = $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.controle'))->getContent().$this->get(route('admin.paiements'))->getContent();

    foreach ([CLE_SECRETE_TEST, SECRET_WEBHOOK_TEST, 'PUBLIQUE-DE-TEST'] as $secret) {
        expect($toutesLesTables)->not->toContain($secret)->and($pages)->not->toContain($secret)->and($reponse->getContent())->not->toContain($secret);
        foreach (glob(storage_path('logs/*.log')) ?: [] as $fichier) {
            expect(file_get_contents($fichier))->not->toContain($secret);
        }
    }
    expect($paiement->fresh()->donnees_brutes)->not->toHaveKey('secret');
});
