<?php

use App\Noyau\Enums\Role;
use App\Noyau\Livewire\AccepterInvitation;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Courriel;
use App\Parametres\Services\Parametres;
use App\Sejours\Livewire\GestionPersonnel;
use App\Sejours\Mail\InvitationPersonnelMail;
use App\Sejours\Models\InvitationPersonnel;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Services\Photos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function inviter($gerant, string $email = 'yao@exemple.com', array $droits = [true, true]): string
{
    Mail::fake();
    $residence = creerResidence($gerant);
    Livewire::actingAs($gerant)->test(GestionPersonnel::class)
        ->set('residenceId', $residence->id)->set('nom', 'Yao (gardien)')->set('email', $email)->set('telephone', '07 55 44 33 22')
        ->set('droitCheckin', $droits[0])->set('droitCalendrier', $droits[1])
        ->call('inviter')->assertSee('Invitation envoyée');
    $lien = null;
    Mail::assertQueued(InvitationPersonnelMail::class, function ($mail) use (&$lien) {
        $lien = $mail->lien;

        return true;
    });

    return basename($lien);
}

it('le gérant invite du personnel : l\'invité crée son accès et n\'a que les droits donnés', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $jeton = inviter($gerant, droits: [false, true]);
    $invitation = InvitationPersonnel::firstOrFail();
    expect($invitation->empreinte)->not->toContain($jeton)->and($invitation->peut_gerer_calendrier)->toBeTrue();

    Livewire::test(AccepterInvitation::class, ['jeton' => $jeton])->assertSee('Rejoindre')
        ->set('motDePasse', 'court')->call('accepter')->assertHasErrors('motDePasse')
        ->set('motDePasse', 'mot-de-passe-solide')->call('accepter')->assertRedirect(route('gerant.calendrier'));

    $personnel = Utilisateur::where('email', 'yao@exemple.com')->firstOrFail();
    $membre = MembreResidence::where('utilisateur_id', $personnel->id)->firstOrFail();
    expect($personnel->aRole(Role::Personnel))->toBeTrue()->and($personnel->aRole(Role::Gerant))->toBeFalse()
        ->and($membre->peut_gerer_calendrier)->toBeTrue()->and($membre->peut_gerer_checkin_checkout)->toBeFalse()
        ->and($personnel->telephone)->toBe('0755443322')
        ->and(auth()->id())->toBe($personnel->id);

    // Le lien ne marche qu'une fois.
    auth()->logout();
    Livewire::test(AccepterInvitation::class, ['jeton' => $jeton])->assertSee('Invitation expirée');
});

it('une invitation expire après 7 jours et un faux lien ne donne rien', function () {
    $jeton = inviter(creerUtilisateur(Role::Gerant));

    Livewire::test(AccepterInvitation::class, ['jeton' => 'faux-lien'])->assertSee('Invitation expirée');
    $this->travel(8)->days();
    Livewire::test(AccepterInvitation::class, ['jeton' => $jeton])->assertSee('Invitation expirée');
    expect(Utilisateur::where('email', 'yao@exemple.com')->exists())->toBeFalse();
});

it('refuse d\'inviter une adresse qui appartient à un compte gérant, ou sans droit choisi', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $autreGerant = creerUtilisateur(Role::Gerant);
    $residence = creerResidence($gerant);
    Mail::fake();

    Livewire::actingAs($gerant)->test(GestionPersonnel::class)
        ->set('residenceId', $residence->id)->set('nom', 'X')->set('email', $autreGerant->email)->set('telephone', '0700000009')
        ->call('inviter')->assertSee('Cette adresse e-mail est déjà utilisée par un compte gérant.');
    Livewire::actingAs($gerant)->test(GestionPersonnel::class)
        ->set('residenceId', $residence->id)->set('nom', 'X')->set('email', 'x@exemple.com')->set('telephone', '0700000009')->set('droitCheckin', false)->set('droitCalendrier', false)
        ->call('inviter')->assertSee('au moins une chose');
    Mail::assertNothingQueued();
});

it('un gérant n\'invite que pour ses résidences', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $residenceDuVoisin = creerResidence(creerUtilisateur(Role::Gerant));

    Livewire::actingAs($gerant)->test(GestionPersonnel::class)
        ->set('residenceId', $residenceDuVoisin->id)->set('nom', 'X')->set('email', 'x@exemple.com')->set('telephone', '0700000009')
        ->call('inviter')->assertForbidden();
    expect(InvitationPersonnel::count())->toBe(0);
});

it('le gérant retire l\'accès : le membre perd son rôle ; un autre gérant ne le peut pas', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $jeton = inviter($gerant);
    Livewire::test(AccepterInvitation::class, ['jeton' => $jeton])->set('motDePasse', 'mot-de-passe-solide')->call('accepter');
    auth()->logout();
    $membre = MembreResidence::firstOrFail();

    Livewire::actingAs(creerUtilisateur(Role::Gerant))->test(GestionPersonnel::class)->call('retirer', $membre->id)->assertForbidden();
    expect(MembreResidence::count())->toBe(1);

    Livewire::actingAs($gerant)->test(GestionPersonnel::class)->call('retirer', $membre->id)->assertSee('Accès retiré.');
    expect(MembreResidence::count())->toBe(0)->and(Utilisateur::where('email', 'yao@exemple.com')->first()->aRole(Role::Personnel))->toBeFalse();
});

it('un compte existant accepte l\'invitation seulement connecté avec la bonne adresse', function () {
    $gerant = creerUtilisateur(Role::Gerant);
    $existant = creerUtilisateur(Role::Client);
    $jeton = inviter($gerant, $existant->email);

    Livewire::test(AccepterInvitation::class, ['jeton' => $jeton])->call('accepter')->assertSee('Connectez-vous avec le compte');
    expect(MembreResidence::count())->toBe(0);

    Livewire::actingAs(creerUtilisateur(Role::Client))->test(AccepterInvitation::class, ['jeton' => $jeton])->call('accepter')->assertSee('Connectez-vous avec le compte');

    Livewire::actingAs($existant)->test(AccepterInvitation::class, ['jeton' => $jeton])->call('accepter')->assertRedirect();
    expect(MembreResidence::where('utilisateur_id', $existant->id)->exists())->toBeTrue()->and($existant->fresh()->aRole(Role::Personnel))->toBeTrue();
});

// --- E-mails ---------------------------------------------------------------------------------------

it('sans réglage e-mail, les messages sont seulement écrits dans le journal ; avec réglages, ils passent par le serveur saisi', function () {
    Courriel::appliquerLesReglages();
    expect(config('mail.default'))->toBe('log');

    Parametres::definir('email.hote', 'smtp.exemple.test');
    Parametres::definir('email.utilisateur', 'gesimo');
    Parametres::definir('email.mot_de_passe', 'mdp-smtp', secret: true);
    Courriel::appliquerLesReglages();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.exemple.test')
        ->and(config('mail.mailers.smtp.password'))->toBe('mdp-smtp'); // déchiffré en mémoire, jamais écrit en clair en base
});

// --- Photos ----------------------------------------------------------------------------------------

it('ne garde jamais le fichier envoyé : un faux « .jpg » qui contient du code est refusé', function () {
    Storage::fake('public');
    $residence = creerResidence();
    $piege = UploadedFile::fake()->createWithContent('photo.jpg', "<?php echo 'piraté'; ?>");

    expect(fn () => Photos::enregistrer($piege, $residence))->toThrow(InvalidArgumentException::class, 'n\'est pas une image valide');
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('réencode la photo en JPEG et limite à 8 photos par résidence', function () {
    Storage::fake('public');
    $residence = creerResidence();

    $photo = Photos::enregistrer(UploadedFile::fake()->image('a.png', 3000, 2000), $residence);
    expect($photo->chemin)->toEndWith('.jpg')->not->toContain('a.png');
    foreach (range(2, 8) as $i) {
        Photos::enregistrer(UploadedFile::fake()->image("p{$i}.jpg"), $residence);
    }

    expect(fn () => Photos::enregistrer(UploadedFile::fake()->image('neuvieme.jpg'), $residence))->toThrow(InvalidArgumentException::class, 'au plus 8 photos');
});
