<?php

namespace App\Sejours\Services;

use App\Paiement\Services\MiseEnAvantService;
use App\Sejours\Models\Unite;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/** Recherche : seulement les logements réellement libres, avec le prix total du séjour. */
class Disponibilites
{
    public function __construct(private TarifCalculateur $tarifs) {}

    /**
     * @param  list<int>  $equipements  identifiants d'équipements exigés (tous)
     * @return Collection<int, array{unite: Unite, total: int}>
     */
    public function rechercher(
        CarbonInterface $arrivee,
        CarbonInterface $depart,
        ?string $commune = null,
        int $personnes = 1,
        ?int $budgetMax = null,
        array $equipements = [],
        int $limite = 60,
    ): Collection {
        $unites = Unite::query()
            ->where('active', true)
            ->where('capacite', '>=', max(1, $personnes))
            ->whereHas('residence', function ($q) use ($commune) {
                $q->where('statut_verification', 'verifiee');
                if ($commune) {
                    $q->where('commune', $commune);
                }
            })
            ->whereNotExists(fn (Builder $q) => $this->occupation($q, $arrivee, $depart))
            ->when($equipements, function ($q) use ($equipements) {
                foreach ($equipements as $id) {
                    $q->whereHas('equipements', fn ($e) => $e->where('equipement.id', $id));
                }
            })
            ->with(['residence.photos', 'photos', 'tarifs', 'equipements'])
            ->limit($limite)
            ->get();

        $enAvant = MiseEnAvantService::residencesEnAvant();

        $resultats = $unites
            ->map(fn (Unite $u) => ['unite' => $u, 'total' => $this->tarifs->total($u, $arrivee, $depart), 'sponsorise' => in_array($u->residence_id, $enAvant, true)])
            ->filter(fn (array $l) => $l['total'] !== null && ($budgetMax === null || $l['total'] <= $budgetMax))
            ->values();

        // RG-19 : les annonces « Sponsorisé » passent en premier, dans un ordre qui change à chaque visite (équité) ;
        // les autres suivent, du moins cher au plus cher.
        return $resultats->where('sponsorise', true)->shuffle()
            ->concat($resultats->where('sponsorise', false)->sortBy('total'))
            ->values();
    }

    public function estLibre(Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): bool
    {
        return ! Unite::query()->whereKey($unite->getKey())
            ->whereExists(fn (Builder $q) => $this->occupation($q, $arrivee, $depart))
            ->exists();
    }

    private function occupation(Builder $q, CarbonInterface $arrivee, CarbonInterface $depart): Builder
    {
        return $q->selectRaw('1')->from('occupation_unite')
            ->whereColumn('occupation_unite.unite_id', 'unite.id')
            ->whereRaw("occupation_unite.periode && daterange(?, ?, '[)')", [$arrivee->toDateString(), $depart->toDateString()]);
    }
}
