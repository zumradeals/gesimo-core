<?php

use App\Noyau\Enums\Role;
use App\Sejours\Livewire\CalendrierGerant;
use App\Sejours\Livewire\EditerResidence;
use App\Sejours\Models\DocumentVerification;
use App\Sejours\Services\PiecesVerification;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

it('les boutons et onglets refusent une résidence devenue incomplète', function (int $destination) {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);

    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('nom', '  ')->set('commune', '')
        ->call('allerEtape', $destination)
        ->assertSet('etape', 1)->assertHasErrors(['nom', 'commune']);
    expect($residence->fresh()->nom)->not->toBe('');
})->with([2, 3]);

it('le passage suivant enregistre les modifications valides de la résidence', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('nom', 'Villa modifiée')->call('allerEtape', 2)->assertSet('etape', 2)->assertHasNoErrors();
    expect($residence->fresh()->nom)->toBe('Villa modifiée');
});

it('refuse le passage aux pièces sans logement actif avec tarif', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = creerResidence($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('allerEtape', 3)->assertSet('etape', 2)->assertSee('au moins un logement avec son prix');
});

it('contrôle le logement en cours avant de quitter son étape même si un autre logement existe', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('nouveauLogement')->set('uNom', 'Nouveau studio')->set('uPrix', '')
        ->call('allerEtape', 3)->assertSet('etape', 2)->assertHasErrors(['uPrix']);
    expect($residence->unites()->count())->toBe(1);
});

it('enregistre le logement valide en cours lors du passage suivant', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = creerResidence($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('nouveauLogement')->set('uNom', 'Studio')->set('uPrix', '15 000')
        ->call('allerEtape', 3)->assertSet('etape', 3)->assertHasNoErrors();
    expect($residence->unites()->count())->toBe(1);
});

it('la soumission directe ne contourne pas les champs obligatoires', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('commune', '')->call('soumettre')->assertSet('etape', 1)->assertHasErrors(['commune']);
    expect($residence->fresh()->soumise_le)->toBeNull();
});

it('enregistre le justificatif choisi lors de la soumission puis conserve les documents après rechargement', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('justificatif', UploadedFile::fake()->image('mandat.png'))->call('soumettre')->assertRedirect(route('gerant.residences'));
    $soumise = $residence->fresh()->soumise_le;
    $chemins = $residence->documents()->pluck('chemin')->all();
    foreach ($chemins as $chemin) {
        Storage::disk('local')->assertExists($chemin);
        Storage::disk('public')->assertMissing($chemin);
    }
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence->fresh()])
        ->call('soumettre')->assertRedirect(route('gerant.residences'));
    expect($residence->documents()->pluck('chemin')->all())->toBe($chemins)
        ->and($residence->fresh()->soumise_le->equalTo($soumise))->toBeTrue()
        ->and(DB::table('journal_audit')->where('action', 'residence.soumise')->where('objet_id', $residence->id)->count())->toBe(1);
});

it('enregistrer sans nouvelle pièce conserve la validation et le badge', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant, ['statut_verification' => 'verifiee', 'badge_verifie' => true]);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('envoyerPieces')->assertSee('Aucun nouveau justificatif sélectionné');
    expect($residence->fresh())->statut_verification->toBe('verifiee')->badge_verifie->toBeTrue();
});

it('la soumission sans justificatif de gestion est autorisée', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('soumettre')->assertRedirect(route('gerant.residences'));
    expect($residence->fresh()->soumise_le)->not->toBeNull()->and($residence->documents()->count())->toBe(0);
});

it('refuse un mauvais format et un justificatif trop lourd sans succès ni enregistrement partiel', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('justificatif', UploadedFile::fake()->create('virus.txt', 20, 'text/plain'))
        ->call('envoyerPieces')->assertHasErrors(['justificatif'])->assertDontSee('Justificatif enregistré pour contrôle');
    expect($residence->documents()->count())->toBe(0);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('justificatif', UploadedFile::fake()->create('mandat.pdf', 5121, 'application/pdf'))
        ->call('envoyerPieces')->assertHasErrors(['justificatif' => 'max']);
});

it('un échec de stockage ne produit pas un faux document enregistré', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    $this->actingAs($gerant);
    $disque = Mockery::mock(FilesystemAdapter::class);
    $disque->shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($disque);

    expect(fn () => PiecesVerification::enregistrer($residence, ['justificatif_droit_gestion' => UploadedFile::fake()->create('mandat.pdf', 20, 'application/pdf')]))
        ->toThrow(ValidationException::class);
    expect($residence->documents()->count())->toBe(0);
});

it('une erreur de base conserve les anciens documents et retire les nouveaux fichiers', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    $this->actingAs($gerant);
    PiecesVerification::enregistrer($residence, ['justificatif_droit_gestion' => UploadedFile::fake()->image('mandat.jpg')]);
    $avant = Storage::disk('local')->allFiles('documents');
    DocumentVerification::saving(fn () => throw new RuntimeException('Panne simulée'));
    try {
        expect(fn () => PiecesVerification::enregistrer($residence, ['justificatif_droit_gestion' => UploadedFile::fake()->image('nouvelle.jpg')]))
            ->toThrow(ValidationException::class);
        expect(Storage::disk('local')->allFiles('documents'))->toBe($avant)
            ->and($residence->documents()->sole()->chemin)->toBe($avant[0]);
    } finally {
        DocumentVerification::flushEventListeners();
    }
});

it('un justificatif refusé ou indisponible ne bloque pas une soumission', function (string $raison) {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    $this->actingAs($gerant);
    PiecesVerification::enregistrer($residence, ['justificatif_droit_gestion' => UploadedFile::fake()->image('mandat.jpg')]);
    $document = $residence->documents()->sole();
    if ($raison === 'refuse') {
        $document->update(['statut' => 'refuse']);
    } else {
        Storage::disk('local')->delete($document->chemin);
    }
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('soumettre')->assertRedirect(route('gerant.residences'));
    expect($residence->fresh()->soumise_le)->not->toBeNull();
})->with(['refuse', 'absent']);

it('soumettre une résidence déjà validée avec badge ne la dépublie pas', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('justificatif', UploadedFile::fake()->image('mandat.jpg'))
        ->call('soumettre');
    $residence->update(['statut_verification' => 'verifiee', 'badge_verifie' => true]);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence->fresh()])
        ->call('soumettre')->assertHasNoErrors()->assertRedirect(route('gerant.residences'));
    expect($residence->fresh())->statut_verification->toBe('verifiee')->badge_verifie->toBeTrue();
});

it('un justificatif de gestion ajouté ne dépublie pas une résidence', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant, ['statut_verification' => 'verifiee', 'badge_verifie' => true]);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('justificatif', UploadedFile::fake()->image('mandat.jpg'))->call('envoyerPieces')->assertHasNoErrors()->assertSee('Justificatif enregistré');
    expect($residence->fresh())->statut_verification->toBe('verifiee');
});

it('refuse les prix invalides sans transformer leur valeur', function (string $prix) {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = creerResidence($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('nouveauLogement')->set('uNom', 'Studio')->set('uPrix', $prix)
        ->call('enregistrerLogement')->assertHasErrors(['uPrix']);
    expect($residence->unites()->count())->toBe(0);
})->with(['-15 000', '15.50', '15,50', '15abc000', '0']);

it('accepte les espaces ordinaires et insécables dans un prix entier', function (string $prix) {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = creerResidence($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('nouveauLogement')->set('uNom', 'Studio')->set('uPrix', $prix)
        ->call('enregistrerLogement')->assertHasNoErrors();
    expect($residence->unites()->sole()->tarifs()->sole()->prix_nuit)->toBe(15000);
})->with(['15 000', "15\u{00A0}000", "15\u{202F}000"]);

it('le calendrier reste accessible sans logement et permet de choisir une autre résidence', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $vide = creerResidence($gerant, ['nom' => 'A vide']);
    $pleine = dossierGerantComplet($gerant, ['nom' => 'B avec logement']);
    Livewire::actingAs($gerant)->withQueryParams(['residenceId' => $vide->id])->test(CalendrierGerant::class)
        ->assertOk()->assertSee('pas encore de logement actif')->assertSee('B avec logement')->assertSet('uniteId', null)
        ->set('residenceId', $pleine->id)->assertSee('Bloquer des dates')->assertSet('uniteId', $pleine->unites()->sole()->id)
        ->set('residenceId', $vide->id)->assertSee('pas encore de logement actif')->assertSet('uniteId', null);
});

it('un logement désactivé ne casse pas le calendrier et une action directe reste refusée', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = dossierGerantComplet($gerant);
    $residence->unites()->update(['active' => false]);
    Livewire::actingAs($gerant)->test(CalendrierGerant::class)->assertOk()->assertSee('pas encore de logement actif')
        ->set('debut', '2026-11-01')->set('fin', '2026-11-03')->call('bloquer')->assertHasErrors(['debut']);
    expect(DB::table('blocage_calendrier')->count())->toBe(0);
});
