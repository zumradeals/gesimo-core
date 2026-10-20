<?php

namespace App\Noyau\Livewire;

use App\Noyau\Mail\MessageGesimo;
use App\Noyau\Models\MessageContact;
use App\Noyau\Services\Courriel;
use App\Parametres\Services\Parametres;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Formulaire de contact : le message est gardé en base puis transmis par e-mail à l'adresse de l'équipe (réglage contact.email). */
#[Title('Contact')]
class Contact extends Component
{
    public string $nom = '';

    public string $contact = '';

    public string $message = '';

    public bool $envoye = false;

    public ?string $erreur = null;

    public function mount(): void
    {
        $u = auth()->user();
        if ($u !== null) {
            $this->nom = $u->nom;
            $this->contact = (string) $u->email;
        }
    }

    public function envoyer(): void
    {
        $this->erreur = null;
        $this->validate([
            'nom' => 'required|string|max:120',
            'contact' => 'required|string|max:190',
            'message' => 'required|string|min:5|max:3000',
        ], [
            'nom.required' => 'Indiquez votre nom.',
            'contact.required' => 'Indiquez un e-mail ou un téléphone pour qu\'on puisse vous répondre.',
            'message.required' => 'Votre message est vide. Dites-nous en quelques mots comment nous aider.',
            'message.min' => 'Votre message est trop court. Dites-nous en quelques mots comment nous aider.',
            'message.max' => 'Votre message est trop long (3 000 caractères au maximum).',
        ]);

        $cle = 'contact:'.request()->ip();
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            $this->erreur = 'Merci de patienter un peu avant de renvoyer un message.';

            return;
        }
        RateLimiter::hit($cle, 3600);

        MessageContact::create(['nom' => $this->nom, 'contact' => $this->contact, 'message' => $this->message, 'adresse_ip' => request()->ip()]);

        $equipe = Parametres::obtenir('contact.email');
        if ($equipe !== null && $equipe !== '') {
            try {
                Courriel::appliquerLesReglages();
                Mail::to($equipe)->send(new MessageGesimo('Nouveau message de contact : '.mb_substr($this->nom, 0, 60), "De : {$this->nom} ({$this->contact})\n\n{$this->message}"));
            } catch (\Throwable $e) {
                report($e); // le message reste en base : rien n'est perdu
            }
        }
        $this->envoye = true;
        $this->reset('message');
    }

    public function render()
    {
        return view('livewire.noyau.contact', ['emailEquipe' => Parametres::obtenir('contact.email')]);
    }
}
