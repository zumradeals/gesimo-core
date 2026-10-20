<?php

namespace App\Noyau\Livewire;

use App\Noyau\Enums\Role;
use App\Noyau\Exceptions\ConnexionRefusee;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Connexion as ServiceConnexion;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Se connecter')]
class Connexion extends Component
{
    public string $telephone = '';

    public string $motDePasse = '';

    public string $code = '';

    public bool $codeEnvoye = false;

    public ?string $erreur = null;

    public ?string $info = null;

    public function seConnecter(ServiceConnexion $connexion)
    {
        $this->reinitialiser();
        $this->validate(['telephone' => 'required', 'motDePasse' => 'required'], [], ['telephone' => 'Le téléphone', 'motDePasse' => 'Le mot de passe']);

        try {
            $utilisateur = $connexion->parMotDePasse($this->telephone, $this->motDePasse, request()->ip());
        } catch (ConnexionRefusee $e) {
            $this->motDePasse = '';
            $this->erreur = $e->getMessage();

            return null;
        }

        return $this->destination($utilisateur);
    }

    public function envoyerLien(ServiceConnexion $connexion): void
    {
        $this->reinitialiser();
        $this->validate(['telephone' => 'required'], [], ['telephone' => 'Le téléphone']);

        try {
            $connexion->envoyerLienEmail($this->telephone, request()->ip());
        } catch (ConnexionRefusee $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $this->info = 'Si ce numéro a un compte, un lien vient d\'être envoyé à l\'e-mail du compte. Il est valable 15 minutes.';
    }

    public function envoyerCode(ServiceConnexion $connexion): void
    {
        $this->reinitialiser();
        $this->validate(['telephone' => 'required'], [], ['telephone' => 'Le téléphone']);

        try {
            $connexion->envoyerCodeSms($this->telephone, request()->ip());
            $this->codeEnvoye = true;
            $this->info = 'Si ce numéro a un compte, un code vient d\'être envoyé par SMS.';
        } catch (ConnexionRefusee $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function verifierCode(ServiceConnexion $connexion)
    {
        $this->reinitialiser();
        $this->validate(['code' => 'required'], [], ['code' => 'Le code']);

        try {
            return $this->destination($connexion->verifierCodeSms($this->telephone, $this->code, request()->ip()));
        } catch (ConnexionRefusee $e) {
            $this->code = '';
            $this->erreur = $e->getMessage();

            return null;
        }
    }

    public function render()
    {
        return view('livewire.noyau.connexion', ['smsActif' => app(ServiceConnexion::class)->smsActif()]);
    }

    private function destination(Utilisateur $utilisateur)
    {
        $defaut = match (true) {
            $utilisateur->estAdmin() => route('admin.residences'),
            $utilisateur->aRole(Role::Gerant) => route('gerant.calendrier'),
            default => route('accueil'),
        };

        return redirect()->intended($defaut);
    }

    private function reinitialiser(): void
    {
        $this->erreur = null;
        $this->info = null;
        $this->resetErrorBag();
    }
}
