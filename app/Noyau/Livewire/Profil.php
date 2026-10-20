<?php

namespace App\Noyau\Livewire;

use App\Noyau\Services\Audit;
use App\Noyau\Services\Telephone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mon profil')]
class Profil extends Component
{
    public string $nom = '';

    public string $telephone = '';

    public string $email = '';

    public string $ancienMotDePasse = '';

    public string $nouveauMotDePasse = '';

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(): void
    {
        $u = auth()->user();
        [$this->nom, $this->telephone, $this->email] = [$u->nom, $u->telephone, (string) $u->email];
    }

    public function enregistrer(): void
    {
        $this->message = $this->erreur = null;
        $u = auth()->user();
        $this->telephone = Telephone::normaliser($this->telephone);
        $this->email = mb_strtolower(trim($this->email));

        $d = $this->validate([
            'nom' => 'required|string|max:120',
            'telephone' => ['required', fn ($a, $v, $echec) => Telephone::estValide($v) ? null : $echec('Le numéro doit avoir 10 chiffres.'), Rule::unique('utilisateur', 'telephone')->ignore($u->id)],
            'email' => ['required', 'email', 'max:190', Rule::unique('utilisateur', 'email')->ignore($u->id)],
        ], [
            'telephone.unique' => 'Ce numéro a déjà un compte.',
            'email.unique' => 'Cette adresse e-mail a déjà un compte.',
        ], ['nom' => 'Votre nom', 'telephone' => 'Le téléphone', 'email' => 'L\'e-mail']);

        $u->update(['nom' => trim($d['nom']), 'telephone' => $d['telephone'], 'email' => $d['email']]);
        Audit::enregistrer('compte.profil_modifie', $u, [], $u);
        $this->message = 'Vos informations sont enregistrées.';
    }

    public function changerMotDePasse(): void
    {
        $this->message = $this->erreur = null;
        $u = auth()->user();
        $this->validate(['nouveauMotDePasse' => 'required|string|min:8|max:100'], [], ['nouveauMotDePasse' => 'Le nouveau mot de passe']);

        if (! Hash::check($this->ancienMotDePasse, $u->mot_de_passe)) {
            $this->erreur = 'L\'ancien mot de passe est incorrect.';

            return;
        }
        $u->update(['mot_de_passe' => $this->nouveauMotDePasse]);
        Audit::enregistrer('compte.mot_de_passe_change', $u, [], $u);
        $this->reset('ancienMotDePasse', 'nouveauMotDePasse');
        $this->message = 'Votre mot de passe est changé.';
    }

    public function render()
    {
        return view('livewire.noyau.profil');
    }
}
