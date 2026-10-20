<?php

namespace App\Sejours\Exceptions;

use RuntimeException;

/** Une règle de réservation n'est pas respectée. Le message est affichable tel quel à la personne. */
class ReservationImpossible extends RuntimeException
{
    public static function datesIndisponibles(?string $detail = null): self
    {
        return new self($detail ?? 'Désolé, ces dates ne sont plus libres. Choisissez d\'autres dates.');
    }
}
