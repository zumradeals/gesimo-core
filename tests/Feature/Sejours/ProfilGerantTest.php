<?php

use App\Noyau\Enums\Role;
use App\Sejours\Livewire\Admin\ListeVerifications;
use App\Sejours\Livewire\Admin\ValiderResidence;
use App\Sejours\Livewire\Admin\VerifierGerant;
use App\Sejours\Livewire\EditerResidence;
use App\Sejours\Livewire\ProfilGerant as PageProfil;
use App\Sejours\Models\ProfilGerant;
use App\Sejours\Services\Disponibilites;
use App\Sejours\Services\DossierGerant;
use App\Sejours\Services\PiecesVerification;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function profilSansIdentite($gerant): ProfilGerant
{
    $p = ProfilGerant::pour($gerant);
    $p->documents()->delete();

    return $p->fresh();
}

it('enregistre le profil privé et la présentation publique sans rendre l’adresse lisible en base', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    Livewire::actingAs($gerant)->test(PageProfil::class)->set('onglet', 'informations')
        ->set('adresse', 'Adresse privée du gérant à ne pas publier')
        ->set('entreprise', 'Maison Naya')->set('nomPublic', 'Maison Naya')
        ->set('communePublique', 'Cocody')->set('presentation', 'Bienvenue à Abidjan.')
        ->set('logo', UploadedFile::fake()->image('portrait.png', 1200, 800))
        ->call('enregistrer')->assertHasNoErrors()->assertSee('Votre profil est enregistré.');
    $p = $gerant->profilGerant->fresh();
    expect($p->adresse)->toBe('Adresse privée du gérant à ne pas publier')
        ->and(DB::table('profil_gerant')->where('id', $p->id)->value('adresse'))->not->toContain('Adresse privée')
        ->and($p->nom_public)->toBe('Maison Naya');
    Storage::disk('public')->assertExists($p->logo_chemin);
    [$largeur, , $type] = getimagesizefromstring(Storage::disk('public')->get($p->logo_chemin));
    expect($largeur)->toBeLessThanOrEqual(480)->and($type)->toBe(IMAGETYPE_JPEG);
});

it('refuse de changer silencieusement un nom dont l’identité est déjà validée', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    Livewire::actingAs($gerant)->test(PageProfil::class)->set('nomComplet', 'Autre identité')
        ->set('adresse', 'Adresse privée')->call('enregistrer')->assertHasErrors(['nomComplet']);
    expect($gerant->profilGerant->fresh()->nom_complet)->toBe($gerant->nom);
});

it('réserve la correction du nom légal à l’administrateur sans changer le nom public', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = $gerant->profilGerant;
    $nomPublic = $p->nom_public;
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(VerifierGerant::class, ['profil' => $p])
        ->set('nomComplet', '  Identité corrigée après contrôle  ')->call('corrigerNom')->assertHasNoErrors();
    expect($p->fresh()->nom_complet)->toBe('Identité corrigée après contrôle')->and($p->fresh()->nom_public)->toBe($nomPublic);
    $this->actingAs($gerant)->get(route('admin.gerant', $p))->assertForbidden();
});

it('centralise les documents pour deux résidences sans copie par résidence ni certification automatique', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = profilSansIdentite($gerant);
    creerResidence($gerant);
    creerResidence($gerant);
    Livewire::actingAs($gerant)->test(PageProfil::class)
        ->set('identite', UploadedFile::fake()->image('cni.jpg'))
        ->set('certificat_residence', UploadedFile::fake()->create('certificat.pdf', 20, 'application/pdf'))
        ->call('envoyerDocuments')->assertHasNoErrors()->assertSee('Documents envoyés pour contrôle');
    expect($p->documents()->count())->toBe(2)->and($p->identiteValidee())->toBeFalse()->and($p->estCertifie())->toBeFalse()
        ->and(DB::table('document_verification')->count())->toBe(0);
    foreach ($p->documents as $d) {
        Storage::disk('local')->assertExists($d->chemin);
        Storage::disk('public')->assertMissing($d->chemin);
    }
});

it('valide l’identité et le certificat indépendamment et exige un motif de refus', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = profilSansIdentite($gerant);
    $this->actingAs($gerant);
    DossierGerant::enregistrer($p, ['identite' => UploadedFile::fake()->image('cni.jpg'), 'certificat_residence' => UploadedFile::fake()->image('certificat.jpg')]);
    $identite = $p->dernierDocument('identite');
    $certificat = $p->dernierDocument('certificat_residence');
    $admin = creerUtilisateur(Role::Admin);
    $page = Livewire::actingAs($admin)->test(VerifierGerant::class, ['profil' => $p]);
    $page->call('accepter', $certificat->id)->assertHasNoErrors();
    expect($p->fresh()->estCertifie())->toBeFalse();
    $page->call('refuser', $identite->id)->assertHasErrors(['motif']);
    $page->call('accepter', $identite->id)->assertHasNoErrors();
    expect($p->fresh()->identiteValidee())->toBeTrue()->and($p->fresh()->estCertifie())->toBeTrue();
});

it('une certification refusée ne bloque pas les annonces dont l’identité reste acceptée', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = dossierGerantComplet($gerant, ['statut_verification' => 'verifiee']);
    $p = $gerant->profilGerant;
    $this->actingAs($gerant);
    DossierGerant::enregistrer($p, ['certificat_residence' => UploadedFile::fake()->image('certificat.jpg')]);
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(VerifierGerant::class, ['profil' => $p])
        ->set('motif', 'Document illisible')->call('refuser', $p->dernierDocument('certificat_residence')->id)->assertHasNoErrors();
    expect($p->fresh()->estCertifie())->toBeFalse()->and($r->fresh()->estPubliee())->toBeTrue();
});

it('conserve l’identité acceptée pendant un renouvellement et remplace la preuve seulement après accord', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = $gerant->profilGerant;
    $avant = $p->documentValide('identite');
    $this->actingAs($gerant);
    DossierGerant::enregistrer($p, ['identite' => UploadedFile::fake()->image('nouvelle.jpg')]);
    $nouvelle = $p->dernierDocument('identite');
    expect($p->identiteValidee())->toBeTrue()->and($p->documentValide('identite')->id)->toBe($avant->id);
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(VerifierGerant::class, ['profil' => $p])->call('accepter', $nouvelle->id)->assertHasNoErrors();
    expect($p->documentValide('identite')->id)->toBe($nouvelle->id)->and($avant->fresh()->remplace_le)->not->toBeNull();
    Storage::disk('local')->assertExists($avant->chemin);
});

it('le refus d’une nouvelle pièce conserve la précédente déjà acceptée', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = $gerant->profilGerant;
    $ancien = $p->documentValide('identite');
    $this->actingAs($gerant);
    DossierGerant::enregistrer($p, ['identite' => UploadedFile::fake()->image('nouvelle.jpg')]);
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(VerifierGerant::class, ['profil' => $p])
        ->set('motif', 'Photo floue')->call('refuser', $p->dernierDocument('identite')->id)->assertHasNoErrors();
    expect($p->identiteValidee())->toBeTrue()->and($p->documentValide('identite')->id)->toBe($ancien->id);
});

it('refuse les documents invalides ou trop lourds sans inscription partielle', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = profilSansIdentite($gerant);
    Livewire::actingAs($gerant)->test(PageProfil::class)
        ->set('identite', UploadedFile::fake()->image('cni.jpg'))
        ->set('certificat_residence', UploadedFile::fake()->create('certificat.txt', 20, 'text/plain'))
        ->call('envoyerDocuments')->assertHasErrors(['certificat_residence']);
    expect($p->documents()->count())->toBe(0);
    Livewire::actingAs($gerant)->test(PageProfil::class)
        ->set('identite', UploadedFile::fake()->create('cni.pdf', 5121, 'application/pdf'))
        ->call('envoyerDocuments')->assertHasErrors(['identite']);
});

it('refuse la validation d’un document absent du stockage', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = profilSansIdentite($gerant);
    $d = $p->documents()->create(['type' => 'identite', 'chemin' => 'introuvable.pdf']);
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(VerifierGerant::class, ['profil' => $p])
        ->call('accepter', $d->id)->assertHasErrors(['documents']);
    expect($p->identiteValidee())->toBeFalse();
});

it('les documents privés ne sont consultables que par les admins et leur lecture est auditée', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = $gerant->profilGerant;
    $d = $p->documentValide('identite');
    $url = route('admin.document-gerant', $d);
    $this->get($url)->assertRedirect(route('connexion'));
    $this->actingAs($gerant)->get($url)->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Client))->get($url)->assertForbidden();
    $this->actingAs(creerUtilisateur(Role::Admin))->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect(DB::table('journal_audit')->where('action', 'gerant.document_consulte')->where('objet_id', $d->id)->exists())->toBeTrue();
});

it('un gérant ne peut ni décider de sa certification ni agir sur les documents d’un autre profil', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $autre = creerUtilisateur(Role::Gerant);
    Livewire::actingAs($gerant)->test(VerifierGerant::class, ['profil' => $gerant->profilGerant])->assertForbidden();
    $this->actingAs($gerant);
    expect(fn () => DossierGerant::enregistrer($autre->profilGerant, ['identite' => UploadedFile::fake()->image('x.jpg')]))->toThrow(HttpException::class);
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(VerifierGerant::class, ['profil' => $gerant->profilGerant])
        ->call('accepter', $autre->profilGerant->documentValide('identite')->id)->assertNotFound();
});

it('un client ou membre du personnel ne peut ouvrir le profil gérant', function (Role $role) {
    $u = creerUtilisateur($role);
    Livewire::actingAs($u)->test(PageProfil::class)->assertForbidden();
    $this->actingAs($u)->get(route('gerant.profil'))->assertForbidden();
})->with([Role::Client, Role::Personnel]);

it('une identité non acceptée bloque la soumission même en appel direct', function (string $statut) {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = profilSansIdentite($gerant);
    if ($statut !== 'absent') {
        $p->documents()->create(['type' => 'identite', 'chemin' => 'nonvalide.pdf', 'statut' => $statut, 'motif_refus' => $statut === 'refuse' ? 'À refaire' : null]);
    }
    $r = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $r])
        ->call('soumettre')->assertSet('etape', 3)->assertSee('Votre pièce d’identité doit être validée');
    expect($r->fresh()->soumise_le)->toBeNull();
})->with(['absent', 'en_attente', 'refuse']);

it('l’admin ne peut publier sans identité acceptée ni attribuer un badge sans justificatif', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    profilSansIdentite($gerant);
    $r = dossierGerantComplet($gerant);
    $admin = creerUtilisateur(Role::Admin);
    Livewire::actingAs($admin)->test(ValiderResidence::class, ['residence' => $r])->call('valider')->assertHasErrors(['decision']);
    identiteGerantValidee($gerant);
    Livewire::actingAs($admin)->test(ValiderResidence::class, ['residence' => $r])->set('badge', true)->call('valider')->assertHasErrors(['decision']);
    expect($r->fresh()->statut_verification)->toBe('en_attente');
});

it('la certification du gérant et le badge sont indépendants et facultatifs pour publier', function (bool $certificat, bool $gestion) {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = $gerant->profilGerant;
    if ($certificat) {
        $p->documents()->create(['type' => 'certificat_residence', 'chemin' => 'certificat.pdf', 'statut' => 'accepte']);
    }
    $r = dossierGerantComplet($gerant);
    $autreResidence = dossierGerantComplet($gerant, ['nom' => 'Autre résidence']);
    if ($gestion) {
        $this->actingAs($gerant);
        PiecesVerification::enregistrer($r, ['justificatif_droit_gestion' => UploadedFile::fake()->image('mandat.jpg')]);
    }
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(ValiderResidence::class, ['residence' => $r])
        ->set('badge', $gestion)->call('valider')->assertHasNoErrors();
    expect($r->fresh()->estPubliee())->toBeTrue()->and($r->fresh()->aBadgeGestion())->toBe($gestion)
        ->and($p->estCertifie())->toBe($certificat)->and($autreResidence->fresh()->aBadgeGestion())->toBeFalse();
})->with([[false, false], [true, false], [false, true], [true, true]]);

it('le refus du justificatif de gestion ne dépublie pas la résidence', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = dossierGerantComplet($gerant, ['statut_verification' => 'verifiee']);
    $this->actingAs($gerant);
    PiecesVerification::enregistrer($r, ['justificatif_droit_gestion' => UploadedFile::fake()->image('mandat.jpg')]);
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(ValiderResidence::class, ['residence' => $r])
        ->set('motifGestion', 'Document ne concernant pas cette résidence')->call('refuserJustificatif', $r->documents()->sole()->id)->assertHasNoErrors();
    expect($r->fresh()->estPubliee())->toBeTrue()->and($r->fresh()->aBadgeGestion())->toBeFalse();
});

it('le profil public ne divulgue aucun document ou renseignement privé et filtre les offres', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = $gerant->profilGerant;
    $p->update(['nom_public' => 'Maison Naya', 'nom_complet' => 'Identité privée distincte', 'adresse' => 'Adresse confidentielle introuvable publiquement', 'presentation' => '<script>secret()</script>']);
    dossierGerantComplet($gerant, ['nom' => 'Résidence publiée', 'statut_verification' => 'verifiee']);
    $brouillon = dossierGerantComplet($gerant, ['nom' => 'Brouillon confidentiel']);
    $brouillon->unites()->update(['nom' => 'Logement encore confidentiel']);
    $this->get(route('hebergeur', $p->slug))->assertOk()->assertSee('Maison Naya')
        ->assertDontSee('Adresse confidentielle')->assertDontSee('Identité privée distincte')->assertDontSee($gerant->telephone)
        ->assertDontSee($gerant->email)->assertDontSee('Logement encore confidentiel')->assertDontSee('dossiers-gerants/')
        ->assertDontSee('<script>secret()</script>', false)->assertSee('&lt;script&gt;secret()', false);
});

it('les annonces sans identité acceptée sont absentes de tous les accès publics et de la réservation', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    profilSansIdentite($gerant);
    $r = dossierGerantComplet($gerant, ['nom' => 'Annonce interdite', 'statut_verification' => 'verifiee']);
    $u = $r->unites()->sole();
    $p = $gerant->profilGerant->fresh();
    $this->get(route('hebergeur', $p->slug))->assertNotFound();
    $this->get(route('logement', $u))->assertNotFound();
    $this->get(route('accueil'))->assertDontSee($u->nom);
    $resultats = app(Disponibilites::class)->rechercher(CarbonImmutable::today()->addDay(), CarbonImmutable::today()->addDays(2));
    expect($resultats)->toBeEmpty();
    $this->actingAs(creerUtilisateur(Role::Client))->get(route('reserver', $u))->assertNotFound();
});

it('le gérant voit son aperçu privé même sans offre publiée mais un visiteur ne le voit pas', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = $gerant->profilGerant;
    $this->get(route('hebergeur', $p->slug))->assertNotFound();
    $reponse = $this->actingAs($gerant)->get(route('gerant.profil.apercu'))->assertOk()->assertSee('Aperçu de votre profil public');
    expect($reponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('le suivi après soumission remplace le formulaire par une confirmation dans la liste', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $r = dossierGerantComplet($gerant);
    Livewire::actingAs($gerant)->test(EditerResidence::class, ['residence' => $r])->call('soumettre')->assertRedirect(route('gerant.residences'));
    $this->actingAs($gerant)->get(route('gerant.residences'))->assertOk()->assertSee('Votre résidence')->assertSee('Suivre mon dossier')->assertDontSee('Envoyer pour validation →');
});

it('la file administrative comprend les documents personnels et les justificatifs de gestion', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $p = profilSansIdentite($gerant);
    $p->update(['nom_public' => 'Dossier à vérifier']);
    $p->documents()->create(['type' => 'identite', 'chemin' => 'cni.pdf']);
    Livewire::actingAs(creerUtilisateur(Role::Admin))->test(ListeVerifications::class)->assertSee('Dossier à vérifier');
});

it('la reprise conserve les fichiers et signale les anciennes identités divergentes sans choisir', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $simple = creerUtilisateur(Role::Gerant);
    $a = creerResidence($gerant, ['badge_verifie' => true]);
    $b = creerResidence($gerant);
    $c = creerResidence($simple);
    foreach ([[$a, 'ancien/a.pdf'], [$b, 'ancien/b.pdf'], [$c, 'ancien/c.pdf']] as [$r, $chemin]) {
        Storage::disk('local')->put($chemin, 'Ancienne pièce');
        $r->documents()->create(['type' => 'piece_gerant', 'chemin' => $chemin, 'statut' => 'accepte', 'verifie_le' => now()]);
    }
    $migration = require database_path('migrations/2026_10_25_000001_centraliser_dossier_gerant.php');
    $migration->down();
    $migration->up();
    $reprise = ProfilGerant::where('utilisateur_id', $gerant->id)->sole();
    expect($reprise->reprise_a_revoir)->toBeTrue()->and($reprise->identiteValidee())->toBeFalse()->and($reprise->documents()->count())->toBe(2);
    expect(ProfilGerant::where('utilisateur_id', $simple->id)->sole()->identiteValidee())->toBeTrue()
        ->and($a->fresh()->badge_verifie)->toBeTrue()->and($a->fresh()->aBadgeGestion())->toBeFalse();
    Storage::disk('local')->assertExists('ancien/a.pdf');
    expect(Storage::disk('local')->allFiles('archives-dossiers-gerants'))->not->toBeEmpty();
});
