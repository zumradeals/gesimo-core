<?php

namespace App\Noyau\Services\Sms;

use RuntimeException;

/** Fournisseur par défaut : il n'en existe pas encore. Le SMS reste donc désactivé. */
class FournisseurSmsNonConfigure implements FournisseurSms
{
    public function estConfigure(): bool
    {
        return false;
    }

    public function envoyer(string $telephone, string $message): void
    {
        throw new RuntimeException('Aucun fournisseur SMS n\'est configuré.');
    }
}
