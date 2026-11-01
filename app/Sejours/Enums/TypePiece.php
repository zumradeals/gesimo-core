<?php

namespace App\Sejours\Enums;

/** Pièces d'identité acceptées pour un voyageur. */
enum TypePiece: string
{
    case Cni = 'cni';
    case Passeport = 'passeport';
    case TitreSejour = 'titre_sejour';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Cni => 'Carte nationale d\'identité',
            self::Passeport => 'Passeport',
            self::TitreSejour => 'Titre de séjour',
            self::Autre => 'Autre pièce officielle',
        };
    }

    /** @return array<string, string> */
    public static function choix(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->libelle()])->all();
    }
}
