<?php

namespace App\Noyau\Livewire;

use App\Noyau\Enums\Role;
use App\Noyau\Exceptions\ConnexionRefusee;
use App\Noyau\Services\Connexion as ServiceConnexion;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Le lien reçu par e-mail ouvre cette page ; il ne sert qu'au clic sur le bouton.
 * (Un antivirus ou une messagerie qui « visite » le lien ne le consomme donc pas.)
 */
#[Title('Connexion par lien')]
class ConnexionParLien extends Component
{
    public string $jeton = '';

    public bool $valide = false;

    public ?string $erreur = null;

    public function mount(string $jeton, ServiceConnexion $connexion): void
    {
        $this->jeton = $jeton;
        $this->valide = $connexion->lienValide($jeton) !== null;
    }

    public function entrer(ServiceConnexion $connexion)
    {
        try {
            $utilisateur = $connexion->consommerLien($this->jeton);
        } catch (ConnexionRefusee $e) {
            $this->valide = false;
            $this->erreur = $e->getMessage();

            return null;
        }

        return redirect()->intended($utilisateur->aRole(Role::Gerant) ? route('gerant.calendrier') : route('accueil'));
    }

    public function render()
    {
        return view('livewire.noyau.connexion-par-lien');
    }
}
