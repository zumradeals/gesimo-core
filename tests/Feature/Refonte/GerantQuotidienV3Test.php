<?php

use App\Noyau\Enums\Role;
use App\Sejours\Livewire\AccesResidence;
use App\Sejours\Livewire\VerificationGerant;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\EtatDocument;
use App\Sejours\Services\Itineraire;
use App\Sejours\Services\PiecesVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Lot 5 : gérant au quotidien, vérification (identité + bail) et itinéraire du voyageur (contrat docs/contrats/gerant-quotidien-v3.md).

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    configurerGeniusPay();
});

/** Réservation payée et confirmée sur une résidence dont on choisit les attributs. */
function resaLot5(object $test, array $residence = []): Reservation
{
    $r = reservationAPayer(['residence' => $residence, 'arrivee' => prochainVendredi()]);
    payerEtConfirmer($test, fauxGeniusPay(), $r);

    return $r->fresh();
}

// ---------------------------------------------------------------------------------------------------------------------
// Itinéraire
// ---------------------------------------------------------------------------------------------------------------------

it('lit la position dans un lien de carte complet, et refuse tout le reste', function (string $lien, ?array $attendu) {
    expect(Itineraire::positionDepuisLien($lien))->toBe($attendu);
})->with([
    'google arobase' => ['https://www.google.com/maps/place/Residence/@5.359900,-3.987000,17z/data=!3m1', [5.3599, -3.987]],
    'google q' => ['https://www.google.com/maps?q=5.35,-4.0', [5.35, -4.0]],
    'google !3d !4d' => ['https://www.google.com/maps/place/x/data=!3d5.31!4d-4.02', [5.31, -4.02]],
    'waze' => ['https://waze.com/ul?ll=5.30,-4.01&navigate=yes', [5.3, -4.01]],
    'lien court sans position' => ['https://maps.app.goo.gl/AbCdEf', null],
    'autre site' => ['https://exemple.test/maps?q=5.3,-4.0', null],
    'pas un lien' => ['5.3,-4.0', null],
    'hors limites' => ['https://www.google.com/maps/@95.0,-3.9,17z', null],
]);

it('construit les liens Google Maps, Waze et Plans, et le message du chauffeur', function () {
    $r = creerResidence(null, ['nom' => 'Résidence Papaye', 'commune' => 'Cocody', 'quartier' => 'Djibi', 'adresse' => 'Rue des Jardins', 'reperes' => 'Portail bleu', 'latitude' => 5.3599, 'longitude' => -3.987]);

    $liens = Itineraire::liens($r);
    expect($liens['google'])->toBe('https://www.google.com/maps/dir/?api=1&destination=5.3599,-3.987')
        ->and($liens['waze'])->toContain('ll=5.3599,-3.987')->and($liens['plans'])->toContain('daddr=5.3599,-3.987');
    expect(Itineraire::messageChauffeur($r))->toContain('Résidence Papaye')->toContain('Rue des Jardins, Djibi, Cocody')->toContain('Repères : Portail bleu')->toContain('destination=5.3599');
    expect(Itineraire::lienWhatsapp($r))->toStartWith('https://wa.me/?text=')->not->toContain(' ');
    expect(Itineraire::liens(creerResidence(null, ['nom' => 'Sans position'])))->toBe([]);
});

it('Comment venir donne tout après confirmation : bouton, repères, appel et WhatsApp', function () {
    $r = resaLot5($this, ['adresse' => 'Rue des Jardins', 'reperes' => 'Portail bleu en face de la pharmacie', 'latitude' => 5.3599, 'longitude' => -3.987]);

    $page = $this->actingAs($r->client)->get(route('reservation.venir', $r))->assertOk();
    $page->assertSee('Me conduire à la résidence', false)->assertSee('waze.com/ul?ll=5.3599,-3.987', false)->assertSee('Portail bleu en face de la pharmacie', false)
        ->assertSee('Rue des Jardins', false)->assertSee('wa.me/?text=', false)->assertSee('tel:'.$r->unite->residence->gerant->telephone, false);
    // Le détail y renvoie.
    $this->actingAs($r->client)->get(route('reservation.detail', $r))->assertSee(route('reservation.venir', $r), false);
});

it('Comment venir sans position renvoie aux repères et à l\'appel, sans bouton de carte', function () {
    $r = resaLot5($this, ['reperes' => 'Portail bleu']);

    $this->actingAs($r->client)->get(route('reservation.venir', $r))->assertOk()
        ->assertSee('n’a pas encore indiqué la position', false)->assertSee('Portail bleu', false)->assertDontSee('Me conduire à la résidence', false)->assertDontSee('wa.me/', false);
});

it('Comment venir avant la confirmation ne donne rien de précis', function () {
    $r = reservationAPayer(['residence' => ['adresse' => 'Rue secrète 12', 'reperes' => 'Repère secret', 'latitude' => 5.36, 'longitude' => -3.98]]);

    $this->actingAs($r->client)->get(route('reservation.venir', $r))->assertOk()
        ->assertSee('après la confirmation de votre réservation', false)->assertDontSee('Rue secrète 12', false)->assertDontSee('Repère secret', false)->assertDontSee('destination=5.36', false)->assertSee(route('payer', $r), false);
});

it('Comment venir est réservé au voyageur de la réservation', function () {
    $r = resaLot5($this);

    $this->actingAs(creerUtilisateur(Role::Client))->get(route('reservation.venir', $r))->assertNotFound();
    auth()->logout();
    $this->get(route('reservation.venir', $r))->assertRedirect();
});

// ---------------------------------------------------------------------------------------------------------------------
// Accès à la résidence (côté gérant)
// ---------------------------------------------------------------------------------------------------------------------

it('le gérant enregistre position, adresse et repères ; personne d\'autre ne le peut', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant);

    Livewire::actingAs($gerant)->test(AccesResidence::class, ['residence' => $r->id])
        ->call('positionRecue', 5.3599, -3.987)->assertSee('Position relevée')
        ->set('adresse', 'Rue des Jardins')->set('reperes', 'Portail bleu')->call('enregistrer')->assertSee('Accès enregistré');
    expect($r->fresh())->latitude->toEqual(5.3599)->longitude->toEqual(-3.987)->adresse->toBe('Rue des Jardins')->reperes->toBe('Portail bleu');

    $this->actingAs(creerUtilisateur(Role::Gerant))->get(route('gerant.residence.acces', $r))->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('gerant.residence.acces', $r))->assertForbidden();
});

it('le gérant peut coller un lien Google Maps ; un lien sans position est expliqué', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant);

    $page = Livewire::actingAs($gerant)->test(AccesResidence::class, ['residence' => $r->id])
        ->set('lien', 'https://maps.app.goo.gl/AbCdEf')->call('lireLien')->assertSee('ne contient pas de position');
    $page->set('lien', 'https://www.google.com/maps/@5.3,-4.0,17z')->call('lireLien')->assertSet('latitude', '5.3')->assertSet('longitude', '-4');
    $page->call('positionRefusee')->assertSee('Nous n’avons pas pu lire votre position');
});

it('les repères trop longs sont refusés en phrase simple', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant);

    Livewire::actingAs($gerant)->test(AccesResidence::class, ['residence' => $r->id])
        ->set('reperes', str_repeat('a', 301))->call('enregistrer')->assertSee('Vos repères sont trop longs (300 caractères au maximum).');
});

it('placer la résidence la première fois, ou la bouger de quelques mètres, ne la dépublie pas ; la déplacer loin la fait revoir', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant);

    $page = Livewire::actingAs($gerant)->test(AccesResidence::class, ['residence' => $r->id]);
    $page->call('positionRecue', 5.3600, -3.9870)->call('enregistrer');
    expect($r->fresh()->statut_verification)->toBe('verifiee');
    $page->call('positionRecue', 5.36003, -3.98702)->call('enregistrer'); // imprécision du GPS
    expect($r->fresh()->statut_verification)->toBe('verifiee');
    $page->call('positionRecue', 5.4200, -3.9870)->call('enregistrer')->assertSee('repasse en vérification'); // ~6,7 km
    expect($r->fresh()->statut_verification)->toBe('en_attente');
});

// ---------------------------------------------------------------------------------------------------------------------
// Vérification du gérant : identité + bail
// ---------------------------------------------------------------------------------------------------------------------

it('Ma vérification montre « À fournir » puis « En contrôle » pour chacun des deux documents', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $gerant->profilGerant->documents()->delete();
    $r = creerResidence($gerant, ['nom' => 'Résidence Papaye', 'statut_verification' => 'en_attente']);

    $page = Livewire::actingAs($gerant)->test(VerificationGerant::class)
        ->assertSee('Ma pièce d’identité', false)->assertSee('Contrat de bail de Résidence Papaye', false)->assertSee('À fournir');
    $page->set('identite', UploadedFile::fake()->image('cni.jpg'))->call('deposerIdentite')->assertSee('Pièce d’identité envoyée');
    $page->set("baux.{$r->id}", UploadedFile::fake()->image('bail.jpg'))->call('deposerBail', $r->id)->assertSee('Contrat de bail envoyé');
    $page->assertSee('En contrôle')->assertDontSee('À fournir');
});

it('Ma vérification refuse un fichier trop lourd et rappelle de choisir un fichier', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $gerant->profilGerant->documents()->delete();
    $r = creerResidence($gerant);

    Livewire::actingAs($gerant)->test(VerificationGerant::class)
        ->call('deposerIdentite')->assertSee('Choisissez d’abord la photo ou le fichier')
        ->set("baux.{$r->id}", UploadedFile::fake()->create('bail.pdf', 6000, 'application/pdf'))->call('deposerBail', $r->id)->assertHasErrors(["baux.{$r->id}"]);
});

it('un bail refusé affiche le motif ; le gérant peut en redéposer un', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant);
    $this->actingAs($gerant);
    PiecesVerification::enregistrer($r, ['justificatif_droit_gestion' => UploadedFile::fake()->image('bail.jpg')]);
    $r->documents()->sole()->update(['statut' => 'refuse', 'motif_refus' => 'Document illisible']);

    Livewire::actingAs($gerant)->test(VerificationGerant::class)->assertSee('Refusé')->assertSee('Motif : Document illisible')->assertSee('Envoyer le contrat de bail');
});

it('« Gérant vérifié » s\'affiche sur la page de vérification et le profil public quand tout est validé', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = $gerant->profilGerant;
    $r = creerResidence($gerant, ['badge_verifie' => true, 'gestion_verifiee_le' => now()]);
    $r->documents()->create(['type' => 'justificatif_droit_gestion', 'chemin' => 'bail.pdf', 'statut' => 'accepte']);
    $unite = $r->unites()->create(['nom' => 'Studio', 'type' => 'studio', 'capacite' => 2]);
    $unite->tarifs()->create(['type_periode' => 'standard', 'prix_nuit' => 10000]);

    Livewire::actingAs($gerant)->test(VerificationGerant::class)->assertSee('✓ Gérant vérifié')->assertSee('Votre dossier est validé');
    $this->get(route('hebergeur', $p->slug))->assertOk()->assertSee('Gérant vérifié')->assertDontSee('Gérant certifié');
    expect(EtatDocument::libelle(EtatDocument::VALIDE))->toBe('Validé');
});

it('Aujourd\'hui demande d\'abord la pièce d\'identité, puis le bail ; une résidence déjà publiée le reste', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $gerant->profilGerant->documents()->delete();
    $r = creerResidence($gerant, ['nom' => 'Résidence Papaye']); // déjà publiée (vérifiée)

    $this->actingAs($gerant)->get(route('gerant.arrivees'))->assertOk()->assertSee('À faire en premier', false)->assertSee('Ajoutez votre pièce d’identité', false)->assertSee(route('gerant.verification'), false);

    identiteGerantValidee($gerant);
    $this->actingAs($gerant)->get(route('gerant.arrivees'))->assertOk()->assertSee('Complétez votre vérification', false)->assertSee('Résidence Papaye', false)->assertSee('restent en ligne', false);
    expect($r->fresh()->estPubliee())->toBeTrue();
});

it('le menu du gérant contient « Ma vérification » et le calendrier prend l\'habillage du gérant', function () {
    $gerant = creerUtilisateur(Role::Gerant);

    $this->actingAs($gerant)->get(route('gerant.calendrier'))->assertOk()->assertSee('Ma vérification', false)->assertSee('espace-gerant', false)->assertDontSee('espace-client', false);
    $this->actingAs($gerant)->get(route('gerant.verification'))->assertOk()->assertSee('Deux documents suffisent', false);
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('gerant.verification'))->assertForbidden();
});

it('les trois écrans du quotidien restent accessibles au gérant avec la nouvelle présentation', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = creerResidence($gerant);
    $u = $r->unites()->create(['nom' => 'Studio', 'type' => 'studio', 'capacite' => 2]);
    $u->tarifs()->create(['type_periode' => 'standard', 'prix_nuit' => 10000]);

    foreach (['gerant.arrivees' => 'Aujourd\'hui', 'gerant.reservations' => 'Réservations', 'gerant.calendrier' => 'Calendrier', 'gerant.telephone' => 'Réservation par téléphone'] as $route => $titre) {
        $this->actingAs($gerant)->get(route($route))->assertOk()->assertSee('mr-page', false)->assertSee($titre, false);
    }
});
