<?php

namespace App\Sejours\Enums;

/** Créneau d'arrivée indiqué (facultatif) par le voyageur, pour aider le gérant à s'organiser. */
enum HeureArriveePrevue: string
{
    case Inconnue = 'inconnue';
    case Avant14h = 'avant_14h';
    case Entre14hEt18h = '14h_18h';
    case Entre18hEt22h = '18h_22h';
    case Apres22h = 'apres_22h';

    public function libelle(): string
    {
        return match ($this) {
            self::Inconnue => 'Je ne sais pas encore',
            self::Avant14h => 'Avant 14 h',
            self::Entre14hEt18h => 'Entre 14 h et 18 h',
            self::Entre18hEt22h => 'Entre 18 h et 22 h',
            self::Apres22h => 'Après 22 h',
        };
    }

    /** @return array<string, string> */
    public static function choix(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->libelle()])->all();
    }
}
