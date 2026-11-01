<?php

namespace App\Sejours\Enums;

/** Motif du séjour déclaré par le voyageur : visible du gérant seulement, sans effet sur le prix. */
enum MotifSejour: string
{
    case Vacances = 'vacances';
    case Affaires = 'affaires';
    case Famille = 'famille';
    case Evenement = 'evenement';
    case Etudes = 'etudes';
    case Soins = 'soins';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Vacances => 'Vacances, tourisme',
            self::Affaires => 'Voyage d\'affaires, travail',
            self::Famille => 'Visite à la famille ou aux proches',
            self::Evenement => 'Événement (mariage, fête, cérémonie)',
            self::Etudes => 'Études ou formation',
            self::Soins => 'Soins médicaux',
            self::Autre => 'Autre',
        };
    }

    /** @return array<string, string> code => libellé, dans l'ordre d'affichage */
    public static function choix(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->libelle()])->all();
    }
}
