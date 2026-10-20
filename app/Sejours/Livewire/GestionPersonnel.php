<?php

namespace App\Sejours\Livewire;

use App\Noyau\Enums\Role;
use App\Noyau\Models\RoleUtilisateur;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Noyau\Services\Courriel;
use App\Noyau\Services\Telephone;
use App\Sejours\Mail\InvitationPersonnelMail;
use App\Sejours\Models\InvitationPersonnel;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\Residence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mon personnel')]
class GestionPersonnel extends Component
{
    public ?int $residenceId = null;

    public string $nom = '';

    public string $email = '';

    public string $telephone = '';

    public bool $droitCheckin = true;

    public bool $droitCalendrier = true;

    public ?string $message = null;

    public ?string $erreur = null;

    public function inviter()
    {
        $this->message = $this->erreur = null;
        $this->email = mb_strtolower(trim($this->email));
        $this->telephone = Telephone::normaliser($this->telephone);

        $this->validate([
            'residenceId' => 'required|integer',
            'nom' => 'required|string|max:150',
            'email' => 'required|email|max:190',
            'telephone' => ['required', fn ($a, $v, $echec) => Telephone::estValide($v) ? null : $echec('Le numéro doit avoir 10 chiffres.')],
        ], [], ['nom' => 'Le nom', 'email' => 'L\'e-mail', 'telephone' => 'Le téléphone', 'residenceId' => 'La résidence']);

        if (! $this->droitCheckin && ! $this->droitCalendrier) {
            $this->erreur = 'Choisissez au moins une chose que cette personne peut faire.';

            return;
        }

        $residence = Residence::where('gerant_id', auth()->id())->find($this->residenceId);
        abort_if($residence === null, 403);
        Gate::authorize('gererPersonnel', $residence);

        $existant = Utilisateur::where('email', $this->email)->first();
        if ($existant?->aRole(Role::Gerant)) {
            $this->erreur = 'Cette adresse e-mail est déjà utilisée par un compte gérant.';

            return;
        }
        if ($existant === null && Utilisateur::where('telephone', $this->telephone)->exists()) {
            $this->erreur = 'Ce numéro de téléphone a déjà un compte avec une autre adresse e-mail.';

            return;
        }

        $secret = Str::random(48);
        $invitation = InvitationPersonnel::create([
            'residence_id' => $residence->id,
            'invite_par' => auth()->id(),
            'nom' => trim($this->nom),
            'email' => $this->email,
            'telephone' => $this->telephone,
            'peut_gerer_checkin_checkout' => $this->droitCheckin,
            'peut_gerer_calendrier' => $this->droitCalendrier,
            'empreinte' => hash_hmac('sha256', $secret, (string) config('app.key')),
            'expire_le' => now()->addDays(7),
        ]);
        Audit::enregistrer('personnel.invite', $invitation, ['residence_id' => $residence->id]);
        Courriel::envoyer($this->email, new InvitationPersonnelMail($invitation->nom, $residence->nom, auth()->user()->nom, route('invitation', $secret)));

        $this->message = 'Invitation envoyée. Le lien est valable 7 jours.';
        $this->nom = $this->email = $this->telephone = '';
    }

    public function retirer(int $membreId): void
    {
        $this->message = $this->erreur = null;
        $membre = MembreResidence::with('residence')->findOrFail($membreId);
        Gate::authorize('gererPersonnel', $membre->residence);

        DB::transaction(function () use ($membre) {
            $utilisateurId = $membre->utilisateur_id;
            $membre->delete();
            // Plus aucune résidence : la personne perd aussi le rôle « personnel ».
            if (! MembreResidence::where('utilisateur_id', $utilisateurId)->exists()) {
                RoleUtilisateur::where('utilisateur_id', $utilisateurId)->where('role', Role::Personnel->value)->delete();
            }
            Audit::enregistrer('personnel.retire', null, ['residence_id' => $membre->residence_id, 'utilisateur_id' => $utilisateurId]);
        });
        $this->message = 'Accès retiré.';
    }

    public function annulerInvitation(int $id): void
    {
        $invitation = InvitationPersonnel::with('residence')->findOrFail($id);
        Gate::authorize('gererPersonnel', $invitation->residence);
        $invitation->update(['expire_le' => now()]);
        $this->message = 'Invitation annulée.';
    }

    public function render()
    {
        $residences = Residence::where('gerant_id', auth()->id())->orderBy('nom')->get();
        $this->residenceId ??= $residences->first()?->id;
        $ids = $residences->pluck('id');

        return view('livewire.sejours.gestion-personnel', [
            'residences' => $residences,
            'membres' => MembreResidence::whereIn('residence_id', $ids)->with(['utilisateur', 'residence'])->get(),
            'invitations' => InvitationPersonnel::whereIn('residence_id', $ids)->whereNull('acceptee_le')->where('expire_le', '>', now())->with('residence')->get(),
        ]);
    }
}
