<?php

namespace App\Noyau\Livewire;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Sejours\Models\InvitationPersonnel;
use App\Sejours\Models\MembreResidence;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Invitation')]
class AccepterInvitation extends Component
{
    public string $jeton = '';

    public string $motDePasse = '';

    public ?string $erreur = null;

    public function mount(string $jeton): void
    {
        $this->jeton = $jeton;
    }

    public function accepter()
    {
        $invitation = $this->invitation();
        if ($invitation === null) {
            $this->erreur = 'Cette invitation n\'est plus valable.';

            return null;
        }

        $existant = Utilisateur::where('email', $invitation->email)->first();
        if ($existant !== null) {
            // Un compte existe déjà : seule cette personne, connectée, peut accepter.
            if (auth()->id() !== $existant->id) {
                $this->erreur = 'Connectez-vous avec le compte de '.$invitation->email.' pour accepter.';

                return null;
            }
            $utilisateur = $existant;
        } else {
            $this->validate(['motDePasse' => 'required|string|min:8|max:100'], [], ['motDePasse' => 'Le mot de passe']);
            if (Utilisateur::where('telephone', $invitation->telephone)->exists()) {
                $this->erreur = 'Ce numéro de téléphone a déjà un compte.';

                return null;
            }
            $utilisateur = null;
        }

        DB::transaction(function () use ($invitation, &$utilisateur) {
            // Une seule acceptation, même si le lien est ouvert deux fois en même temps.
            $pris = InvitationPersonnel::whereKey($invitation->id)->whereNull('acceptee_le')->update(['acceptee_le' => now()]);
            abort_if($pris !== 1, 410);

            $utilisateur ??= Utilisateur::create([
                'nom' => $invitation->nom, 'telephone' => $invitation->telephone, 'email' => $invitation->email,
                'mot_de_passe' => $this->motDePasse,
            ]);
            $utilisateur->attribuerRole(Role::Personnel);
            MembreResidence::updateOrCreate(
                ['residence_id' => $invitation->residence_id, 'utilisateur_id' => $utilisateur->id],
                ['peut_gerer_checkin_checkout' => $invitation->peut_gerer_checkin_checkout, 'peut_gerer_calendrier' => $invitation->peut_gerer_calendrier],
            );
            $invitation->update(['utilisateur_id' => $utilisateur->id]);
            Audit::enregistrer('personnel.invitation_acceptee', $invitation, ['residence_id' => $invitation->residence_id], $utilisateur);
        });

        Auth::guard('web')->login($utilisateur);
        session()->regenerate();

        return redirect()->route($invitation->peut_gerer_calendrier ? 'gerant.calendrier' : 'compte');
    }

    public function render()
    {
        $invitation = $this->invitation();

        return view('livewire.noyau.accepter-invitation', [
            'invitation' => $invitation,
            'compteExiste' => $invitation !== null && Utilisateur::where('email', $invitation->email)->exists(),
            'connecteCommeInvite' => $invitation !== null && auth()->user()?->email === $invitation->email,
        ]);
    }

    private function invitation(): ?InvitationPersonnel
    {
        $invitation = InvitationPersonnel::where('empreinte', hash_hmac('sha256', $this->jeton, (string) config('app.key')))
            ->with('residence.gerant')->first();

        return $invitation?->estValide() ? $invitation : null;
    }
}
