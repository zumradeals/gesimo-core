<?php

use App\Noyau\Enums\Role;
use App\Parametres\Services\Parametres;
use App\Sejours\Livewire\Admin\ListeVerifications;
use App\Sejours\Livewire\Admin\ValiderResidence;
use App\Sejours\Livewire\EditerResidence;
use App\Sejours\Livewire\MesResidences;
use App\Sejours\Models\DocumentVerification;
use App\Sejours\Models\Equipement;
use App\Sejours\Models\Photo;
use App\Sejours\Models\Residence;
use App\Sejours\Services\Disponibilites;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function residenceBrouillon($gerant, array $attributs = []): Residence
{
    return Residence::create($attributs + ['gerant_id' => $gerant->id, 'nom' => 'Les Palmiers', 'commune' => 'Cocody', 'statut_verification' => 'en_attente']);
}

it('un gérant crée sa résidence : elle reste invisible et en brouillon', function () {
    $gerant = creerUtilisateur(Role::Gerant);

    Livewire::actingAs($gerant)->test(EditerResidence::class)
        ->set('nom', 'Résidence Les Palmiers')->set('commune', 'Cocody')->set('quartier', 'Riviera')->set('reglement', 'Pas de fêtes.')
        ->call('enregistrerResidence')
        ->assertRedirect();

    $r = Residence::firstOrFail();
    expect($r->gerant_id)->toBe($gerant->id)
        ->and($r->statut_verification)->toBe('en_attente')
        ->and($r->soumise_le)->toBeNull()
        ->and($r->badge_verifie)->toBeFalse()
        ->and($r->mode_paiement)->toBe('total');
    expect(DB::table('journal_audit')->where('action', 'residence.creee')->exists())->toBeTrue();
});

it('l\'option acompte n\'existe pas tant que la commission n\'est pas réglée, puis respecte le plancher', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $champs = fn ($test) => $test->set('nom', 'R')->set('commune', 'Cocody')->set('modePaiement', 'acompte')->set('pourcentageAcompte', '30');

    $champs(Livewire::actingAs($gerant)->test(EditerResidence::class))->call('enregistrerResidence')
        ->assertSee('pas encore disponible');
    expect(Residence::count())->toBe(0);

    Parametres::definir('commission.pourcentage', '10');
    Parametres::definir('paiement.frais_pourcentage', '2.5'); // plancher : 13 %

    $champs(Livewire::actingAs($gerant)->test(EditerResidence::class))->set('pourcentageAcompte', '12')->call('enregistrerResidence')
        ->assertSee('L\'acompte ne peut pas être inférieur à 13 %.');
    expect(Residence::count())->toBe(0);

    $champs(Livewire::actingAs($gerant)->test(EditerResidence::class))->set('pourcentageAcompte', '13')->call('enregistrerResidence')->assertRedirect();
    expect(Residence::first())->mode_paiement->toBe('acompte')->pourcentage_acompte->toBe(13);
});

it('ajoute un logement avec prix, week-end, fêtes, équipements et photo (réencodée)', function () {
    Storage::fake('public');
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = residenceBrouillon($gerant);
    $climatisation = Equipement::where('code', 'climatisation')->value('id');

    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('nouveauLogement')
        ->set('uNom', 'Chambre climatisée 1')->set('uType', 'chambre')->set('uCapacite', '2')
        ->set('uPrix', '15 000')->set('uPrixWeekEnd', '20 000')
        ->call('ajouterFete')->set('uFetes.0.libelle', 'Noël')->set('uFetes.0.debut', '2026-12-24')->set('uFetes.0.fin', '2026-12-26')->set('uFetes.0.prix', '30 000')
        ->set('uEquipements', [$climatisation])
        ->set('nouvellesPhotos', [UploadedFile::fake()->image('salon.png', 2400, 1600)])
        ->call('enregistrerLogement')
        ->assertHasNoErrors();

    $unite = $residence->unites()->with(['tarifs', 'equipements'])->firstOrFail();
    expect($unite->tarifs->firstWhere('type_periode', 'standard')->prix_nuit)->toBe(15000)
        ->and($unite->tarifs->firstWhere('type_periode', 'week_end')->prix_nuit)->toBe(20000)
        ->and($unite->tarifs->firstWhere('type_periode', 'fete')->prix_nuit)->toBe(30000)
        ->and($unite->equipements->pluck('code')->all())->toBe(['climatisation']);

    $photo = Photo::firstOrFail();
    Storage::disk('public')->assertExists($photo->chemin);
    [$largeur, , $type] = getimagesizefromstring(Storage::disk('public')->get($photo->chemin));
    expect($type)->toBe(IMAGETYPE_JPEG)->and($largeur)->toBeLessThanOrEqual(1600); // allégée pour les connexions lentes
});

it('refuse un logement sans prix, un fichier qui n\'est pas une image, avec des messages simples', function () {
    Storage::fake('public');
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = residenceBrouillon($gerant);

    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('nouveauLogement')->set('uNom', 'Studio')->set('uCapacite', '2')->set('uPrix', '')
        ->call('enregistrerLogement')
        ->assertSee('Le prix par nuit est obligatoire et doit être un nombre entier.');

    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->call('nouveauLogement')->set('uNom', 'Studio')->set('uCapacite', '2')->set('uPrix', '15000')
        ->set('nouvellesPhotos', [UploadedFile::fake()->create('virus.php', 10, 'text/x-php')])
        ->call('enregistrerLogement')
        ->assertSee('Cette photo est trop lourde ou n\'est pas une image.');
    expect($residence->unites()->count())->toBe(0)->and(Photo::count())->toBe(0);
});

it('la soumission réutilise le dossier gérant et le justificatif facultatif reste privé', function () {
    Storage::fake('public');
    Storage::fake('local');
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = residenceBrouillon($gerant);
    $test = Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence]);
    $test->call('soumettre')->assertSet('etape', 2)->assertSee('Ajoutez et enregistrez au moins un logement');
    $test->call('nouveauLogement')->set('uNom', 'Studio')->set('uCapacite', '2')->set('uPrix', '15000')
        ->set('nouvellesPhotos', [UploadedFile::fake()->image('a.jpg')])->call('enregistrerLogement');
    $test->set('justificatif', UploadedFile::fake()->create('titre.pdf', 200, 'application/pdf'))->call('soumettre')->assertRedirect(route('gerant.residences'));
    expect($residence->fresh()->soumise_le)->not->toBeNull();
    $document = $residence->documents()->sole();
    Storage::disk('local')->assertExists($document->chemin);
    Storage::disk('public')->assertMissing($document->chemin);
    expect($document->type)->toBe('justificatif_droit_gestion');
    expect($gerant->profilGerant->identiteValidee())->toBeTrue();
});

it('un gérant ne voit et ne modifie que ses propres résidences', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $autre = creerUtilisateur(Role::Gerant);
    $a = residenceBrouillon($gerant, ['nom' => 'Ma résidence à moi']);
    $b = residenceBrouillon($autre, ['nom' => 'Résidence du voisin']);

    Livewire::actingAs($gerant)->test(MesResidences::class)->assertSee('Ma résidence à moi')->assertDontSee('Résidence du voisin');
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $b])->assertForbidden();

    $this->actingAs($gerant)->get(route('gerant.residence', $b))->assertForbidden();
    $this->actingAs($gerant)->get(route('gerant.residence', $a))->assertOk();
});

it('un client ne peut pas créer de résidence ni ouvrir les écrans du gérant', function () {
    $client = creerUtilisateur(Role::Client);

    $this->actingAs($client)->get(route('gerant.residences'))->assertForbidden();
    $this->actingAs($client)->get(route('gerant.residence.nouvelle'))->assertForbidden();
    $this->actingAs($client)->get(route('gerant.calendrier'))->assertForbidden();
    $this->actingAs($client)->get(route('admin.residences'))->assertForbidden();
});

function residenceSoumise($gerant): Residence
{
    $residence = residenceBrouillon($gerant, ['soumise_le' => now()]);
    $unite = $residence->unites()->create(['nom' => 'Studio', 'type' => 'studio', 'capacite' => 2]);
    $unite->tarifs()->create(['type_periode' => 'standard', 'prix_nuit' => 15000]);

    Storage::disk('local')->put('tests-gestion/'.$residence->id.'.pdf', 'Justificatif fictif');
    $residence->documents()->create(['type' => 'justificatif_droit_gestion', 'chemin' => 'tests-gestion/'.$residence->id.'.pdf']);

    return $residence;
}

it('l\'admin voit les résidences soumises, valide avec le badge, et la résidence devient publique', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $admin = creerUtilisateur(Role::Admin);
    $residence = residenceSoumise($gerant);
    residenceBrouillon($gerant, ['nom' => 'Brouillon non envoyé']);
    $vendredi = prochainVendredi();

    expect(app(Disponibilites::class)->rechercher($vendredi, $vendredi->addDay()))->toBeEmpty(); // invisible avant validation

    Livewire::actingAs($admin)->test(ListeVerifications::class)->assertSee('Les Palmiers')->assertDontSee('Brouillon non envoyé');
    Livewire::actingAs($admin)->test(ValiderResidence::class, ['residence' => $residence])
        ->set('badge', true)->call('valider')->assertSee('Résidence validée et mise en ligne.');

    $residence = $residence->fresh();
    expect($residence->statut_verification)->toBe('verifiee')->and($residence->badge_verifie)->toBeTrue()
        ->and($residence->verifiee_par)->toBe($admin->id);
    expect(app(Disponibilites::class)->rechercher($vendredi, $vendredi->addDay()))->toHaveCount(1);
    expect(DB::table('journal_audit')->where('action', 'residence.validee')->where('utilisateur_id', $admin->id)->exists())->toBeTrue();
});

it('l\'admin peut valider sans accorder le badge (le badge est un plus)', function () {
    $residence = residenceSoumise(creerUtilisateur(Role::Gerant));

    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(ValiderResidence::class, ['residence' => $residence])->call('valider');

    expect($residence->fresh())->statut_verification->toBe('verifiee')->badge_verifie->toBeFalse();
});

it('le refus exige un motif, que le gérant voit ; il peut corriger et renvoyer', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = residenceSoumise($gerant);
    $admin = creerUtilisateur(Role::Admin);

    Livewire::actingAs($admin)->test(ValiderResidence::class, ['residence' => $residence])->call('refuser')->assertSee('Indiquez le motif du refus.');
    expect($residence->fresh()->statut_verification)->toBe('en_attente');

    Livewire::actingAs($admin)->test(ValiderResidence::class, ['residence' => $residence])->set('motifRefus', 'Photo floue')->call('refuser');
    expect($residence->fresh())->statut_verification->toBe('refusee')->motif_refus->toBe('Photo floue');

    Livewire::actingAs($gerant)->test(MesResidences::class)->assertSee('Refusée')->assertSee('Photo floue');
    expect(app(Disponibilites::class)->rechercher(prochainVendredi(), prochainVendredi()->addDay()))->toBeEmpty();
});

it('seuls les admins accèdent à la validation ; un gérant ne valide pas sa propre résidence', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = residenceSoumise($gerant);

    $this->actingAs($gerant)->get(route('admin.residence', $residence))->assertForbidden();
    Livewire::actingAs($gerant)->test(ValiderResidence::class, ['residence' => $residence])->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Admin))->get(route('admin.residence', $residence))->assertOk();
    $this->actingAs(creerUtilisateur(Role::SuperAdmin))->get(route('admin.residences'))->assertOk();
});

it('les pièces ne sont lisibles que par l\'équipe Avelis, et chaque lecture est journalisée', function () {
    Storage::fake('local');
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = residenceSoumise($gerant);
    Storage::disk('local')->put('documents/1/secret.pdf', 'contenu');
    $document = DocumentVerification::create(['residence_id' => $residence->id, 'type' => 'piece_gerant', 'chemin' => 'documents/1/secret.pdf']);

    $this->get(route('admin.document', $document))->assertRedirect(route('connexion'));
    $this->actingAs($gerant)->get(route('admin.document', $document))->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('admin.document', $document))->assertForbidden();

    $admin = creerUtilisateur(Role::Admin);
    $this->actingAs($admin)->get(route('admin.document', $document))->assertOk();
    expect(DB::table('journal_audit')->where('action', 'document.consulte')->where('utilisateur_id', $admin->id)->exists())->toBeTrue();
});

it('un gérant peut modifier une résidence déjà en ligne', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residence = creerResidence($gerant);

    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $residence])
        ->set('reglement', 'Nouveau règlement')->call('enregistrerResidence')->assertSee('Résidence enregistrée.');

    expect($residence->fresh()->reglement)->toBe('Nouveau règlement');
});
