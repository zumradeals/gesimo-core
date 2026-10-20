<?php

namespace App\Noyau\Services\Sms;

/** Interface « fournisseur SMS » : un vrai fournisseur pourra être branché sans toucher au reste. */
interface FournisseurSms
{
    /** Vrai seulement si le fournisseur est réellement utilisable (clés saisies, etc.). */
    public function estConfigure(): bool;

    public function envoyer(string $telephone, string $message): void;
}
