<?php

namespace App\Sejours\Services;

use App\Sejours\Models\Unite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Prix d'un séjour = somme des prix de chaque nuit. Pour une nuit :
 *  1. une période « fête » datée qui la couvre l'emporte ;
 *  2. sinon, le prix week-end pour les nuits du vendredi et du samedi ;
 *  3. sinon, le prix normal.
 * Sans prix normal, le logement n'est pas réservable.
 * Le même calcul sert à la recherche, à la fiche et au récapitulatif : le prix affiché est toujours identique.
 */
class TarifCalculateur
{
    /** @return list<array{date: CarbonImmutable, prix: int, type: string}>|null null si le logement n'a pas de prix normal */
    public function detail(Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): ?array
    {
        $tarifs = $unite->tarifs;
        $standard = $tarifs->firstWhere('type_periode', 'standard');
        if ($standard === null) {
            return null;
        }
        $weekEnd = $tarifs->firstWhere('type_periode', 'week_end');
        $fetes = $tarifs->where('type_periode', 'fete');

        $detail = [];
        for ($nuit = CarbonImmutable::parse($arrivee)->startOfDay(); $nuit->lt($depart->copy()->startOfDay()); $nuit = $nuit->addDay()) {
            $fete = $fetes->first(fn ($t) => $nuit->betweenIncluded($t->date_debut, $t->date_fin));

            if ($fete !== null) {
                $detail[] = ['date' => $nuit, 'prix' => (int) $fete->prix_nuit, 'type' => 'fete'];
            } elseif ($weekEnd !== null && in_array($nuit->dayOfWeekIso, [5, 6], true)) {
                $detail[] = ['date' => $nuit, 'prix' => (int) $weekEnd->prix_nuit, 'type' => 'week_end'];
            } else {
                $detail[] = ['date' => $nuit, 'prix' => (int) $standard->prix_nuit, 'type' => 'standard'];
            }
        }

        return $detail;
    }

    public function total(Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): ?int
    {
        $detail = $this->detail($unite, $arrivee, $depart);

        return $detail === null ? null : array_sum(array_column($detail, 'prix'));
    }
}
