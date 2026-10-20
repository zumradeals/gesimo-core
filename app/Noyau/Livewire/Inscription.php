<?php

namespace App\Noyau\Livewire;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Noyau\Services\Telephone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Créer un compte')]
class Inscription extends Component
{
    public string $nom = '';

    public string $telephone = '';

    public string $email = '';

    public string $motDePasse = '';

    /** « client » : réserver un séjour ; « gerant » : proposer ma résidence. */
    public string $profil = 'client';

    public function inscrire()
    {
        $this->telephone = Telephone::normaliser($this->telephone);
        $this->email = mb_strtolower(trim($this->email));

        $donnees = $this->validate([
            'nom' => 'required|string|max:120',
            'telephone' => ['required', fn ($a, $v, $echec) => Telephone::estValide($v) ? null : $echec('Le numéro doit avoir 10 chiffres.'), 'unique:utilisateur,telephone'],
            'email' => 'required|email|max:190|unique:utilisateur,email',
            'motDePasse' => 'required|string|min:8|max:100',
            'profil' => 'required|in:client,gerant',
        ], [
            'telephone.unique' => 'Ce numéro de téléphone a déjà un compte. Connectez-vous.',
            'email.unique' => 'Cette adresse e-mail a déjà un compte. Connectez-vous.',
        ], ['nom' => 'Votre nom', 'telephone' => 'Le téléphone', 'email' => 'L\'e-mail', 'motDePasse' => 'Le mot de passe']);

        $utilisateur = DB::transaction(function () use ($donnees) {
            $u = Utilisateur::create([
                'nom' => trim($donnees['nom']),
                'telephone' => $donnees['telephone'],
                'email' => $donnees['email'],
                'mot_de_passe' => $donnees['motDePasse'],
            ]);
            $u->attribuerRole($donnees['profil'] === 'gerant' ? Role::Gerant : Role::Client);
            Audit::enregistrer('compte.cree', $u, ['profil' => $donnees['profil']], $u);

            return $u;
        });

        Auth::guard('web')->login($utilisateur);
        session()->regenerate();

        return redirect()->route($utilisateur->aRole(Role::Gerant) ? 'gerant.residence.nouvelle' : 'accueil');
    }

    public function render()
    {
        return view('livewire.noyau.inscription');
    }
}
