<?php

use App\Noyau\Enums\Role;
use App\Noyau\Livewire\Inscription;
use App\Noyau\Models\JournalAudit;
use App\Noyau\Models\Notification;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Format;
use App\Paiement\Services\ArriveeService;
use App\Parametres\Exceptions\ReglageInvalide;
use App\Parametres\Services\Parametres;
use App\Parametres\Services\Reglages;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Enums\TypePiece;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Livewire\FicheLogement;
use App\Sejours\Livewire\PieceIdentite;
use App\Sejours\Livewire\Recapitulatif;
use App\Sejours\Livewire\ReservationsGerant;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\PieceVoyageur;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\Disponibilites;
use App\Sejours\Services\PiecesVoyageur;
use App\Sejours\Services\ReglesSejour;
use App\Sejours\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    configurerGeniusPay();
});

/** Informations de l'étape « Vos informations », telles que le service les reçoit. */
function voyageurV3Valide(array $surcharges = []): array
{
    return $surcharges + [
        'nom' => 'Aminata Koné', 'telephone' => '0701020304', 'motif' => 'vacances', 'heure_arrivee' => 'entre_18h_22h_inconnu',
        'message' => null, 'accord_le' => now(),
    ];
}

/** Réservation payée et confirmée, avec les informations du voyageur. */
function resaV3Confirmee($test, array $voyageur = [], ?CarbonImmutable $arrivee = null, ?Utilisateur $client = null, ?object $faux = null): Reservation
{
    $faux ??= fauxGeniusPay();
    $client ??= creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $arrivee ??= prochainVendredi();
    $reservation = app(ReservationService::class)->garderDates($client, $unite, $arrivee, $arrivee->addDays(2), 2, voyageurV3Valide(['heure_arrivee' => '18h_22h'] + $voyageur));
    payerEtConfirmer($test, $faux, $reservation);

    return $reservation->fresh();
}

function photoPieceV3(): UploadedFile
{
    return UploadedFile::fake()->image('cni.jpg', 600, 400);
}

// ====================================================================================================
// Fiche du logement : date d'arrivée + nombre de nuits
// ====================================================================================================

it('la fiche calcule le départ et le total à partir de l\'arrivée et du nombre de nuits', function () {
    $unite = creerLogement(10000, null);
    $v = prochainVendredi();

    $page = Livewire::test(FicheLogement::class, ['unite' => $unite])
        ->set('arrivee', $v->toDateString())
        ->assertSet('nuits', 1)->assertSet('depart', $v->addDay()->toDateString());

    $page->call('plusNuit')->call('plusNuit')->assertSet('nuits', 3)->assertSet('depart', $v->addDays(3)->toDateString())
        ->assertSee('3 nuits × ', false)->assertSee('Total, sans frais cachés')->assertSee('avant 11 h');
    $page->call('moinsNuit')->assertSet('nuits', 2);
    $page->call('fixerNuits', 7)->assertSet('nuits', 7)->assertSet('depart', $v->addDays(7)->toDateString());

    // Changer la date d'arrivée garde le même nombre de nuits : le départ suit.
    $page->set('arrivee', $v->addDays(7)->toDateString())->assertSet('nuits', 7)->assertSet('depart', $v->addDays(14)->toDateString());
});

it('la fiche regroupe les nuits de même prix et garde le détail nuit par nuit à portée de main', function () {
    $unite = creerLogement(15000, 20000); // vendredi et samedi au prix week-end
    $v = prochainVendredi();

    Livewire::test(FicheLogement::class, ['unite' => $unite])->set('arrivee', $v->toDateString())->call('fixerNuits', 3)
        ->assertSee('2 nuits × ', false)->assertSee('(week-end)')->assertSee('1 nuit × ', false)->assertSee('Voir le prix nuit par nuit');
});

it('la fiche respecte le séjour minimum et maximum réglés dans les Paramètres', function () {
    $unite = creerLogement();
    $v = prochainVendredi();
    Parametres::definir('reservation.sejour_min_nuits', '2');
    Parametres::definir('reservation.sejour_max_nuits', '5');

    $page = Livewire::test(FicheLogement::class, ['unite' => $unite])->set('arrivee', $v->toDateString())
        ->assertSet('nuits', 2)->assertSee('Séjour minimum : 2 nuits');
    $page->call('moinsNuit')->assertSet('nuits', 2);          // pas sous le minimum
    $page->call('fixerNuits', 40)->assertSet('nuits', 5);     // pas au-dessus du maximum

    // Un ancien lien ou une adresse trafiquée demande 12 nuits : pas de bouton Réserver, on invite à nous contacter.
    Livewire::withQueryParams(['arrivee' => $v->toDateString(), 'depart' => $v->addDays(12)->toDateString()])
        ->test(FicheLogement::class, ['unite' => $unite])
        ->assertSee('Au-delà de 5 nuits', false)->assertSee(route('contact'), false)->assertDontSee('Vous ne payez rien maintenant');
});

it('les voyageurs ne dépassent jamais la capacité du logement', function () {
    $unite = creerLogement(15000, null, [], 3);
    Livewire::test(FicheLogement::class, ['unite' => $unite])->assertSet('personnes', 2)
        ->call('plusPersonne')->assertSet('personnes', 3)->call('plusPersonne')->assertSet('personnes', 3)
        ->call('moinsPersonne')->call('moinsPersonne')->call('moinsPersonne')->assertSet('personnes', 1);
    Livewire::withQueryParams(['personnes' => 9])->test(FicheLogement::class, ['unite' => $unite])->assertSet('personnes', 3);
});

it('la fiche annonce la vraie date limite d\'annulation gratuite', function () {
    $unite = creerLogement();
    $v = prochainVendredi();
    $attendue = $v->subDay()->translatedFormat('j M');

    Livewire::test(FicheLogement::class, ['unite' => $unite])->set('arrivee', $v->toDateString())
        ->assertSee("Annulation gratuite jusqu'au {$attendue} à 14 h", false);

    $stricte = creerLogement(15000, null, ['politique_annulation' => 'stricte']);
    Livewire::test(FicheLogement::class, ['unite' => $stricte])->set('arrivee', $v->toDateString())
        ->assertDontSee('Annulation gratuite jusqu\'au', false)->assertSee('Conditions d\'annulation précisées avant le paiement', false);
});

it('quand les dates sont prises, la fiche propose les prochaines dates libres pour le même nombre de nuits', function () {
    $unite = creerLogement();
    $v = prochainVendredi();
    insererReservationSql($unite, $v->addDay()->toDateString(), $v->addDays(3)->toDateString());

    $page = Livewire::test(FicheLogement::class, ['unite' => $unite])->set('arrivee', $v->toDateString())->call('fixerNuits', 3)
        ->assertSee('Ces dates ne sont plus libres', false)->assertDontSee('Vous ne payez rien maintenant');

    $propositions = app(Disponibilites::class)->prochainesDatesLibres($unite, $v, 3);
    expect($propositions)->toHaveCount(3)
        ->and($propositions[0]['arrivee']->toDateString())->toBe($v->addDays(3)->toDateString()) // première période libre après l'occupation
        ->and($propositions[1]['arrivee']->gte($propositions[0]['depart']))->toBeTrue();         // elles ne se chevauchent pas
    $page->call('choisirArrivee', $propositions[0]['arrivee']->toDateString())->assertSet('depart', $propositions[0]['depart']->toDateString())
        ->assertSee('Vous ne payez rien maintenant');
});

// ====================================================================================================
// Réglages
// ====================================================================================================

it('les réglages du séjour ont les valeurs recommandées par défaut', function () {
    expect(ReglesSejour::nuitsMin())->toBe(1)->and(ReglesSejour::nuitsMax())->toBe(30)
        ->and(ReglesSejour::heureDepart())->toBe('11:00')->and(ReglesSejour::accesPieceJours())->toBe(7)
        ->and(ReglesSejour::heureLisible('11:00'))->toBe('11 h')->and(ReglesSejour::heureLisible('08:30'))->toBe('8 h 30');
});

it('le séjour minimum ne peut pas dépasser le séjour maximum', function () {
    $admin = creerUtilisateur(Role::SuperAdmin);
    expect(fn () => Reglages::enregistrer('reservation', ['reservation.sejour_min_nuits' => '10', 'reservation.sejour_max_nuits' => '5'], [], $admin, null))
        ->toThrow(ReglageInvalide::class);
    Reglages::enregistrer('reservation', ['reservation.sejour_min_nuits' => '2', 'reservation.sejour_max_nuits' => '14', 'reservation.heure_depart' => '10:30'], [], $admin, null);
    expect(ReglesSejour::nuitsMin())->toBe(2)->and(ReglesSejour::nuitsMax())->toBe(14)->and(ReglesSejour::heureDepart())->toBe('10:30');
});

// ====================================================================================================
// Service de réservation : durée, informations du voyageur
// ====================================================================================================

it('le service refuse un séjour trop court ou trop long, mais pas une réservation prise par téléphone', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $v = prochainVendredi();
    Parametres::definir('reservation.sejour_min_nuits', '2');
    Parametres::definir('reservation.sejour_max_nuits', '4');
    $service = app(ReservationService::class);

    expect(fn () => $service->garderDates($client, $unite, $v, $v->addDay(), 1))->toThrow(ReservationImpossible::class, 'séjour minimum est de 2 nuits');
    expect(fn () => $service->garderDates($client, $unite, $v, $v->addDays(5), 1))->toThrow(ReservationImpossible::class, 'Au-delà de 4 nuits');
    expect($service->garderDates($client, $unite, $v, $v->addDays(4), 1)->fresh()->nombre_nuits)->toBe(4);

    // Le gérant reste libre de saisir un long séjour pris par téléphone.
    $gerant = $unite->residence->gerant;
    expect($service->creerHorsPlateforme($gerant, $unite, $v->addDays(10), $v->addDays(50), 'Client téléphone')->fresh()->nombre_nuits)->toBe(40);
});

it('le service enregistre les informations du voyageur et l\'heure de départ en vigueur', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $v = prochainVendredi();
    Parametres::definir('reservation.heure_depart', '10:00');

    $r = app(ReservationService::class)->garderDates($client, $unite, $v, $v->addDays(2), 2, voyageurV3Valide([
        'motif' => 'autre', 'motif_precision' => 'Entretien d\'embauche', 'heure_arrivee' => '18h_22h', 'message' => "  J'arrive à 20 h  ",
    ]))->fresh();

    expect($r->nom_client)->toBe('Aminata Koné')->and($r->telephone_client)->toBe('0701020304')
        ->and($r->motif_sejour)->toBe('autre')->and($r->motif_precision)->toBe('Entretien d\'embauche')
        ->and($r->heure_arrivee_prevue)->toBe('18h_22h')->and($r->message_gerant)->toBe("J'arrive à 20 h")
        ->and($r->conditions_acceptees_le)->not->toBeNull()->and($r->heure_depart)->toBe('10:00');

    // Les Paramètres changent ensuite : la réservation déjà faite garde son heure de départ.
    Parametres::definir('reservation.heure_depart', '12:00');
    expect($r->fresh()->heure_depart)->toBe('10:00');
});

it('le service refuse un motif absent ou inconnu, un « autre » sans précision et l\'absence d\'accord', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $v = prochainVendredi();
    $service = app(ReservationService::class);

    expect(fn () => $service->garderDates($client, $unite, $v, $v->addDay(), 1, voyageurV3Valide(['motif' => ''])))->toThrow(ReservationImpossible::class, 'motif');
    expect(fn () => $service->garderDates($client, $unite, $v, $v->addDay(), 1, voyageurV3Valide(['motif' => 'piratage'])))->toThrow(ReservationImpossible::class, 'motif');
    expect(fn () => $service->garderDates($client, $unite, $v, $v->addDay(), 1, voyageurV3Valide(['motif' => 'autre'])))->toThrow(ReservationImpossible::class, 'Précisez');
    expect(fn () => $service->garderDates($client, $unite, $v, $v->addDay(), 1, voyageurV3Valide(['accord_le' => null])))->toThrow(ReservationImpossible::class, 'accepter');
    expect(fn () => $service->garderDates($client, $unite, $v, $v->addDay(), 1, voyageurV3Valide(['nom' => '  '])))->toThrow(ReservationImpossible::class, 'nom');
    expect(Reservation::count())->toBe(0);
});

it('la base de données refuse un motif qui n\'existe pas', function () {
    $unite = creerLogement();
    $id = insererReservationSql($unite, prochainVendredi()->toDateString(), prochainVendredi()->addDay()->toDateString());
    expect(codeRefusBase(fn () => DB::table('reservation')->where('id', $id)->update(['motif_sejour' => 'piratage'])))->toBe('23514');
});

// ====================================================================================================
// Étape « Vos informations »
// ====================================================================================================

it('l\'étape « Vos informations » préremplit le voyageur et garde les dates puis mène au paiement', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $v = prochainVendredi();

    $page = Livewire::actingAs($client)->withQueryParams(['arrivee' => $v->toDateString(), 'depart' => $v->addDays(2)->toDateString(), 'personnes' => 2])
        ->test(Recapitulatif::class, ['unite' => $unite])
        ->assertSet('nom', $client->nom)->assertSee('Pourquoi venez-vous ?')->assertSee('Vacances, tourisme')
        ->assertSee('Quelques informations avant de payer')->assertSee('gardées');

    $page->set(informationsVoyageurValides(['motif' => 'evenement']))->assertSee('Événement :')
        ->set('heureArrivee', '18h_22h')->set('message', 'Merci de m\'attendre')
        ->call('garder')->assertHasNoErrors()->assertRedirect(route('payer', Reservation::firstOrFail()));

    $r = Reservation::firstOrFail();
    expect($r->motif_sejour)->toBe('evenement')->and($r->heure_arrivee_prevue)->toBe('18h_22h')->and($r->message_gerant)->toBe('Merci de m\'attendre')
        ->and($r->telephone_client)->toBe('0701020304');
});

it('l\'étape « Vos informations » affiche des messages simples quand il manque quelque chose', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $v = prochainVendredi();

    Livewire::actingAs($client)->withQueryParams(['arrivee' => $v->toDateString(), 'depart' => $v->addDay()->toDateString(), 'personnes' => 1])
        ->test(Recapitulatif::class, ['unite' => $unite])
        ->set('nom', '')->set('telephone', '0701')->call('garder')
        ->assertHasErrors(['nom', 'telephone', 'motif', 'accord'])
        ->assertSee('Indiquez votre nom comme sur votre pièce d\'identité.')
        ->assertSee('Ce numéro semble incomplet')->assertSee('Choisissez le motif de votre séjour.')
        ->assertSee('Cochez la case pour accepter le règlement et les conditions générales.')
        ->set(informationsVoyageurValides(['motif' => 'autre']))->call('garder')->assertHasErrors(['motifPrecision'])
        ->assertSee('Précisez en quelques mots');
    expect(Reservation::count())->toBe(0);
});

it('l\'étape « Vos informations » refuse un séjour hors limites et signale la pièce déjà enregistrée', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $v = prochainVendredi();
    Parametres::definir('reservation.sejour_max_nuits', '3');

    Livewire::actingAs($client)->withQueryParams(['arrivee' => $v->toDateString(), 'depart' => $v->addDays(6)->toDateString(), 'personnes' => 1])
        ->test(Recapitulatif::class, ['unite' => $unite])->assertSee('Au-delà de 3 nuits', false)->assertDontSee('Continuer vers le paiement');

    PiecesVoyageur::enregistrer($client, TypePiece::Cni, photoPieceV3());
    Livewire::actingAs($client)->withQueryParams(['arrivee' => $v->toDateString(), 'depart' => $v->addDays(2)->toDateString(), 'personnes' => 1])
        ->test(Recapitulatif::class, ['unite' => $unite])->assertSee('Votre pièce d\'identité est déjà enregistrée', false);
});

it('un visiteur est invité à se connecter sans perdre ses dates, et reprend sa réservation après inscription', function () {
    $unite = creerLogement();
    $v = prochainVendredi();
    $adresse = route('reserver', ['unite' => $unite->id, 'arrivee' => $v->toDateString(), 'depart' => $v->addDays(2)->toDateString(), 'personnes' => 2]);

    $this->get($adresse)->assertRedirect(route('connexion'));
    $this->flushSession();
    $this->get(route('connexion'))->assertDontSee('Vos dates sont conservées');
    $this->withSession(['url.intended' => $adresse])->get(route('connexion'))->assertSee('Vos dates sont conservées');
    $this->withSession(['url.intended' => $adresse])->get(route('inscription'))->assertSee('Vos dates sont conservées');

    session(['url.intended' => $adresse]);
    Livewire::test(Inscription::class)->set('nom', 'Awa Koné')->set('telephone', '0709090909')->set('email', 'awa@example.test')
        ->set('motDePasse', 'un-mot-de-passe-solide')->set('profil', 'client')->call('inscrire')->assertRedirect($adresse);
});

// ====================================================================================================
// Pièce d'identité du voyageur
// ====================================================================================================

it('la pièce d\'identité est enregistrée chiffrée sur le disque privé et remplace l\'ancienne', function () {
    $client = creerUtilisateur(Role::Client);
    $original = file_get_contents(($photo = photoPieceV3())->getRealPath());

    $piece = PiecesVoyageur::enregistrer($client, TypePiece::Cni, $photo);
    $chemin = $piece->chemin;
    Storage::disk('local')->assertExists($chemin);
    expect(Storage::disk('local')->get($chemin))->not->toContain('JFIF')->and(Storage::disk('local')->get($chemin))->not->toBe($original)
        ->and(PiecesVoyageur::contenu($piece))->toBe($original)
        ->and($piece->mime)->toBe('image/jpeg');
    expect(JournalAudit::where('action', 'voyageur.piece_deposee')->count())->toBe(1);
    expect(json_encode(JournalAudit::where('action', 'voyageur.piece_deposee')->first()->details))->not->toContain($chemin);

    $nouvelle = PiecesVoyageur::enregistrer($client, TypePiece::Passeport, UploadedFile::fake()->image('passeport.png'), now()->addYear()->toDateString());
    expect(PieceVoyageur::where('utilisateur_id', $client->id)->count())->toBe(1)->and($nouvelle->type->value)->toBe('passeport');
    Storage::disk('local')->assertMissing($chemin);
    Storage::disk('local')->assertExists($nouvelle->chemin);
});

it('la pièce d\'identité refuse un mauvais format, un fichier trop lourd et une date de validité dépassée', function () {
    $client = creerUtilisateur(Role::Client);
    $essayer = fn (UploadedFile $f, ?string $date = null) => expect(fn () => PiecesVoyageur::enregistrer($client, TypePiece::Cni, $f, $date))->toThrow(ValidationException::class);

    $essayer(UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'));
    $essayer(UploadedFile::fake()->create('gros.pdf', 6000, 'application/pdf'));
    $essayer(photoPieceV3(), now()->subDay()->toDateString());
    expect(PieceVoyageur::count())->toBe(0);

    // Un PDF reste accepté.
    expect(PiecesVoyageur::enregistrer($client, TypePiece::Autre, UploadedFile::fake()->createWithContent('permis.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"))->mime)->toBe('application/pdf');
});

it('seul un client peut déposer une pièce d\'identité', function () {
    foreach ([Role::Gerant, Role::Admin] as $role) {
        expect(fn () => PiecesVoyageur::enregistrer(creerUtilisateur($role), TypePiece::Cni, photoPieceV3()))->toThrow(HttpException::class);
    }
});

it('le voyageur dépose, remplace puis supprime sa pièce depuis son profil', function () {
    $client = creerUtilisateur(Role::Client);

    $page = Livewire::actingAs($client)->test(PieceIdentite::class, ['source' => 'profil'])->assertSee('Photo de la pièce')
        ->call('enregistrer')->assertSee('Choisissez une photo ou un fichier.')
        ->set('type', 'passeport')->set('fichier', photoPieceV3())->call('enregistrer')->assertSee('Votre pièce d\'identité est enregistrée')->assertSee('Fournie')
        ->assertDispatched('piece-enregistree');
    expect($client->pieceVoyageur->fresh()->type->value)->toBe('passeport');

    $page->set('remplacer', true)->assertSee('Envoyer ma pièce')
        ->set('fichier', UploadedFile::fake()->create('trop.exe', 10, 'application/octet-stream'))->call('enregistrer')->assertSee('Envoyez une photo (JPG, PNG) ou un PDF.');

    $page->set('remplacer', false)->call('supprimer')->assertSee('Votre pièce d\'identité est supprimée');
    expect(PieceVoyageur::count())->toBe(0);
});

it('la pièce ne peut pas être supprimée tant qu\'une réservation confirmée en a besoin', function () {
    $client = creerUtilisateur(Role::Client);
    PiecesVoyageur::enregistrer($client, TypePiece::Cni, photoPieceV3());
    resaV3Confirmee($this, client: $client);

    Livewire::actingAs($client)->test(PieceIdentite::class)->call('supprimer')->assertSee('Vous avez une réservation en cours');
    expect(PieceVoyageur::count())->toBe(1);
});

it('le profil du client propose la pièce d\'identité, pas celui du gérant', function () {
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('profil'))->assertOk()->assertSee('Mon profil voyageur')->assertSee('Ma pièce d\'identité', false)->assertSee('Qui peut voir ma pièce ?');
    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('profil'))->assertOk()->assertDontSee('Ma pièce d\'identité', false);
});

it('la confirmation invite à ajouter la pièce, puis la reconnaît quand elle est fournie', function () {
    $reservation = resaV3Confirmee($this);
    $client = $reservation->client;

    $this->actingAs($client)->get(route('reservation.confirmation', $reservation))->assertOk()
        ->assertSee('Ajoutez votre pièce d\'identité', false)->assertSee('À faire avant votre arrivée')->assertSee('Prochaines étapes');

    PiecesVoyageur::enregistrer($client, TypePiece::Cni, photoPieceV3());
    $this->actingAs($client)->get(route('reservation.confirmation', $reservation))->assertOk()
        ->assertDontSee('Ajoutez votre pièce d\'identité', false)->assertSee('déjà enregistrée');
});

// ====================================================================================================
// Ce que voit et peut ouvrir le gérant
// ====================================================================================================

it('l\'état de la pièce suit la réservation : à fournir, fournie, vérifiée à l\'arrivée', function () {
    $reservation = resaV3Confirmee($this, arrivee: CarbonImmutable::today());
    expect(PiecesVoyageur::etat($reservation))->toBe('a_fournir');

    PiecesVoyageur::enregistrer($reservation->client, TypePiece::Cni, photoPieceV3(), now()->addYear()->toDateString());
    expect(PiecesVoyageur::etat($reservation))->toBe('fournie');

    // Une pièce expirée compte comme « à fournir ».
    PieceVoyageur::where('utilisateur_id', $reservation->client_id)->update(['valide_jusqu' => now()->subDay()]);
    expect(PiecesVoyageur::etat($reservation))->toBe('a_fournir');
    PieceVoyageur::where('utilisateur_id', $reservation->client_id)->update(['valide_jusqu' => now()->addYear()]);

    app(ArriveeService::class)->enregistrerArrivee($reservation, $reservation->unite->residence->gerant, 'cni', 'CI0012345');
    expect(PiecesVoyageur::etat($reservation->fresh()))->toBe('verifiee');

    expect(PiecesVoyageur::etat(Reservation::findOrFail(insererReservationSql(creerUnite(), prochainVendredi()->addDays(40)->toDateString(), prochainVendredi()->addDays(41)->toDateString(), ['origine' => 'hors_plateforme', 'statut' => 'hors_plateforme', 'nom_client' => 'Tél', 'montant_total' => 0, 'acompte' => 0, 'solde' => 0]))))->toBeNull();
});

it('le gérant voit le motif, le message et l\'état de la pièce de ses voyageurs', function () {
    $reservation = resaV3Confirmee($this, ['motif' => 'affaires', 'message' => 'J\'arrive en avion à 20 h']);
    $gerant = $reservation->unite->residence->gerant;

    Livewire::actingAs($gerant)->test(ReservationsGerant::class)->call('detail', $reservation->id)
        ->assertSee('Voyage d\'affaires, travail')->assertSee('J\'arrive en avion à 20 h')->assertSee('Entre 18 h et 22 h')
        ->assertSee('À fournir')->assertSee('Pièce d\'identité non fournie : demandez-la à l\'arrivée', false)->assertDontSee('Voir la pièce');

    PiecesVoyageur::enregistrer($reservation->client, TypePiece::Cni, photoPieceV3());
    Livewire::actingAs($gerant)->test(ReservationsGerant::class)->call('detail', $reservation->id)
        ->assertSee('Fournie')->assertSee(route('gerant.piece-voyageur', $reservation), false);
});

it('le gérant de la réservation ouvre la pièce déchiffrée, et chaque ouverture est journalisée', function () {
    $reservation = resaV3Confirmee($this);
    $photo = photoPieceV3();
    $original = file_get_contents($photo->getRealPath());
    PiecesVoyageur::enregistrer($reservation->client, TypePiece::Cni, $photo);
    $gerant = $reservation->unite->residence->gerant;

    $reponse = $this->actingAs($gerant)->get(route('gerant.piece-voyageur', $reservation))->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($reponse->headers->get('Cache-Control'))->toContain('no-store')->and($reponse->getContent())->toBe($original);
    $this->actingAs($gerant)->get(route('gerant.piece-voyageur', $reservation))->assertOk();
    expect(JournalAudit::where('action', 'voyageur.piece_consultee')->where('utilisateur_id', $gerant->id)->count())->toBe(2);
});

it('personne d\'autre que le gérant et son personnel autorisé ne peut ouvrir la pièce', function () {
    $reservation = resaV3Confirmee($this);
    PiecesVoyageur::enregistrer($reservation->client, TypePiece::Cni, photoPieceV3());
    $residenceId = $reservation->unite->residence_id;
    $url = route('gerant.piece-voyageur', $reservation);

    $this->get($url)->assertRedirect(route('connexion'));
    // Le voyageur lui-même passe par son profil ; l'administration n'y a pas accès (droits minimum).
    foreach ([$reservation->client, creerUtilisateur(Role::Admin), creerUtilisateur(Role::SuperAdmin), creerUtilisateur(Role::Client)] as $intrus) {
        expect($this->actingAs($intrus)->get($url)->status())->toBe(403);
    }
    // Un autre gérant, ou un personnel sans le droit « arrivées et départs ».
    $this->actingAs(creerUtilisateur(Role::Gerant))->get($url)->assertForbidden();
    $calendrier = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $residenceId, 'utilisateur_id' => $calendrier->id, 'peut_gerer_calendrier' => true]);
    $this->actingAs($calendrier)->get($url)->assertForbidden();

    $accueil = creerUtilisateur(Role::Personnel);
    MembreResidence::create(['residence_id' => $residenceId, 'utilisateur_id' => $accueil->id, 'peut_gerer_checkin_checkout' => true]);
    $this->actingAs($accueil)->get($url)->assertOk();
    expect(JournalAudit::where('action', 'voyageur.piece_consultee')->count())->toBe(1); // seules les ouvertures réussies sont journalisées
});

it('la pièce n\'est ouverte que pour une réservation confirmée et pendant la durée réglée après le départ', function () {
    // Réservation en attente de paiement : pas d'accès.
    $enAttente = reservationAPayer();
    PiecesVoyageur::enregistrer($enAttente->client, TypePiece::Cni, photoPieceV3());
    $this->actingAs($enAttente->unite->residence->gerant)->get(route('gerant.piece-voyageur', $enAttente))->assertForbidden();

    $reservation = resaV3Confirmee($this);
    PiecesVoyageur::enregistrer($reservation->client, TypePiece::Cni, photoPieceV3());
    $gerant = $reservation->unite->residence->gerant;
    $url = route('gerant.piece-voyageur', $reservation);
    $departMinuit = $reservation->date_depart->startOfDay();

    $this->actingAs($gerant);
    $this->travelTo($departMinuit->copy()->addDays(7)->setTime(12, 0));
    $this->get($url)->assertOk();                                  // dernier jour d'accès (7 jours après le départ)
    $this->travelTo($departMinuit->copy()->addDays(8)->setTime(9, 0));
    $this->get($url)->assertForbidden();                           // fermé ensuite

    Parametres::definir('reservation.piece_acces_jours', '30');   // le réglage change la durée
    $this->get($url)->assertOk();
    $this->travelBack();
});

it('un voyageur sans pièce ou avec une pièce expirée donne une page introuvable, pas une erreur', function () {
    $reservation = resaV3Confirmee($this);
    $gerant = $reservation->unite->residence->gerant;
    $this->actingAs($gerant)->get(route('gerant.piece-voyageur', $reservation))->assertNotFound();

    PiecesVoyageur::enregistrer($reservation->client, TypePiece::Cni, photoPieceV3());
    PieceVoyageur::where('utilisateur_id', $reservation->client_id)->update(['valide_jusqu' => now()->subDay()]);
    $this->actingAs($gerant)->get(route('gerant.piece-voyageur', $reservation))->assertNotFound();
});

// ====================================================================================================
// Rappel avant l'arrivée
// ====================================================================================================

it('le rappel de pièce part 2 jours avant l\'arrivée, une seule fois, et seulement si elle manque', function () {
    $faux = fauxGeniusPay();
    $sans = resaV3Confirmee($this, arrivee: CarbonImmutable::today()->addDays(2), faux: $faux);
    $avec = resaV3Confirmee($this, arrivee: CarbonImmutable::today()->addDays(2), faux: $faux);
    resaV3Confirmee($this, arrivee: CarbonImmutable::today()->addDays(5), faux: $faux); // trop tôt : pas de rappel
    PiecesVoyageur::enregistrer($avec->client, TypePiece::Cni, photoPieceV3());

    $this->artisan('gesimo:rappels-piece')->assertSuccessful();
    $this->artisan('gesimo:rappels-piece')->assertSuccessful(); // relancée : pas de doublon

    $envoyes = Notification::where('modele', 'rappel_piece')->get();
    expect($envoyes)->toHaveCount(1)->and($envoyes->first()->destinataire_id)->toBe($sans->client_id)
        ->and($envoyes->first()->contenu)->toContain('pièce d\'identité')->and($envoyes->first()->contenu)->toContain($sans->unite->nom);
});

// ====================================================================================================
// Pages et gabarit
// ====================================================================================================

it('les pages du parcours de réservation affichent le site public et la charte orange', function () {
    $reservation = resaV3Confirmee($this);
    $client = $reservation->client;
    $unite = $reservation->unite;
    $q = ['arrivee' => prochainVendredi()->addDays(20)->toDateString(), 'depart' => prochainVendredi()->addDays(22)->toDateString(), 'personnes' => 2];

    $this->get(route('logement', ['unite' => $unite->id]))->assertOk()->assertSee('site-reservation', false);
    $this->actingAs($client)->get(route('reserver', ['unite' => $unite->id] + $q))->assertOk()->assertSee('site-reservation', false)->assertSee('site-utilitaire', false);
    $this->actingAs($client)->get(route('reservation.confirmation', $reservation))->assertOk()->assertSee('site-reservation', false);
    $enAttente = reservationAPayer(['client' => $client]);
    $this->actingAs($client)->get(route('payer', $enAttente))->assertOk()->assertSee('Payez pour confirmer')->assertSee('Vos informations');
    // Les autres pages gardent leur habillage actuel.
    $this->actingAs($client)->get(route('mes-reservations'))->assertOk()->assertDontSee('site-reservation', false);
});

it('l\'étape paiement rappelle le voyageur et son motif, sans recalculer les montants', function () {
    $client = creerUtilisateur(Role::Client);
    $unite = creerLogement();
    $v = prochainVendredi();
    $r = app(ReservationService::class)->garderDates($client, $unite, $v, $v->addDays(2), 2, voyageurV3Valide(['motif' => 'famille']));

    $this->actingAs($client)->get(route('payer', $r))->assertOk()->assertSee('Aminata Koné')->assertSee('Visite à la famille ou aux proches')
        ->assertSee(Format::fcfa($r->acompte));
    expect($r->fresh()->statut)->toBe(StatutReservation::EnAttente);
});
