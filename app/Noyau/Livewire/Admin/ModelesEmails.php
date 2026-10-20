<?php

namespace App\Noyau\Livewire\Admin;

use App\Noyau\Messagerie\Messagerie;
use App\Noyau\Messagerie\ModelesMessages;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Textes des e-mails de la plateforme, modifiables sans toucher au code (réservé au super-administrateur). */
#[Title('Modèles d\'e-mails')]
class ModelesEmails extends Component
{
    public ?string $choisi = null;

    public string $sujet = '';

    public string $corps = '';

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('modifier-parametres'), 403);
    }

    public function modifier(string $code): void
    {
        abort_unless(isset(ModelesMessages::defauts()[$code]), 404);
        $this->message = $this->erreur = null;
        $this->choisi = $code;
        ['sujet' => $this->sujet, 'corps' => $this->corps] = ModelesMessages::obtenir($code);
    }

    public function enregistrer(): void
    {
        $this->message = $this->erreur = null;
        Gate::authorize('modifier-parametres');
        if ($this->choisi === null) {
            return;
        }
        if (trim($this->sujet) === '' || trim($this->corps) === '') {
            $this->erreur = 'Le sujet et le message ne peuvent pas être vides.';

            return;
        }
        if (mb_strlen($this->sujet) > 190 || mb_strlen($this->corps) > 5000) {
            $this->erreur = 'Le texte est trop long.';

            return;
        }
        $inconnus = ModelesMessages::motsInconnus($this->sujet.' '.$this->corps);
        if ($inconnus !== []) {
            $this->erreur = '« {'.$inconnus[0].'} » n\'existe pas. Mots possibles : {'.implode('}, {', array_keys(ModelesMessages::MOTS)).'}.';

            return;
        }
        ModelesMessages::definir($this->choisi, $this->sujet, $this->corps, auth()->user());
        $this->message = 'Modèle enregistré.';
    }

    public function essai(): void
    {
        $this->message = $this->erreur = null;
        Gate::authorize('modifier-parametres');
        if ($this->choisi === null) {
            return;
        }
        $exemple = ['logement' => 'Chambre climatisée 1', 'residence' => 'Les Palmiers', 'dates' => '11 déc. 2026 → 14 déc. 2026', 'montant' => '16 500 FCFA', 'numero' => '123', 'motif' => 'Exemple de motif', 'decision' => 'Exemple de décision'];
        // On envoie les textes enregistrés (pas ceux en cours de saisie) : enregistrez d'abord.
        $envoye = Messagerie::envoyer($this->choisi, auth()->user(), $exemple);
        $this->message = $envoye ? 'Un essai vient de vous être envoyé par e-mail (textes enregistrés).' : null;
        $this->erreur = $envoye ? null : 'L\'essai n\'a pas pu être préparé.';
    }

    public function render()
    {
        return view('livewire.admin.modeles-emails', ['modeles' => ModelesMessages::defauts(), 'mots' => ModelesMessages::MOTS]);
    }
}
