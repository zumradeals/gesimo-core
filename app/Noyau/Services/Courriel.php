<?php

namespace App\Noyau\Services;

use App\Parametres\Services\Parametres;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi d'e-mails avec les réglages saisis dans l'espace Paramètres (clés email.*).
 * Sans réglage, les e-mails sont seulement écrits dans le journal (aucun envoi réel).
 */
class Courriel
{
    public static function envoyer(string $destinataire, Mailable $courriel): void
    {
        self::appliquerLesReglages();
        Mail::to($destinataire)->queue($courriel);
    }

    /** À rappeler avant chaque envoi et chaque tâche d'arrière-plan : les réglages peuvent avoir changé. */
    public static function appliquerLesReglages(): void
    {
        $hote = Parametres::obtenir('email.hote');

        if ($hote === null || $hote === '') {
            config(['mail.default' => 'log']);

            return;
        }

        $chiffrement = Parametres::obtenir('email.chiffrement', 'tls');
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $hote,
                'port' => (int) Parametres::obtenir('email.port', '587'),
                'username' => Parametres::obtenir('email.utilisateur'),
                'password' => Parametres::obtenir('email.mot_de_passe'),
                'scheme' => $chiffrement === 'ssl' ? 'smtps' : 'smtp',
                'timeout' => 15,
            ],
            'mail.from' => [
                'address' => Parametres::obtenir('email.expediteur_adresse', 'ne-pas-repondre@localhost'),
                'name' => Parametres::obtenir('email.expediteur_nom', 'Avelis Séjours'),
            ],
        ]);
    }
}
