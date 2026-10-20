<?php

namespace App\Parametres\Exceptions;

use RuntimeException;

/** Une valeur de réglage est refusée. Les messages sont affichables tels quels, champ par champ. */
class ReglageInvalide extends RuntimeException
{
    /** @param array<string, string> $erreurs clé du réglage (ou « mot_de_passe ») => message */
    public function __construct(public readonly array $erreurs)
    {
        parent::__construct(reset($erreurs) ?: 'Valeur refusée.');
    }
}
