<?php

namespace App\Sejours\Services;

use App\Paiement\Services\MiseEnAvantService;
use App\Sejours\Models\Unite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        array $types = [],
    ): Collection {
        $unites = Unite::query()
            ->where('active', true)
            ->where('capacite', '>=', max(1, $personnes))
            ->whereHas('residence', function ($q) use ($commune) {
                $q->publiee();
                if ($commune) {
                    $q->where('commune', $commune);
                }
            })
            ->whereNotExists(fn (Builder $q) => $this->occupation($q, $arrivee, $depart))
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->when($equipements, function ($q) use ($equipements) {
                foreach ($equipements as $id) {
                    $q->whereHas('equipements', fn ($e) => $e->where('equipement.id', $id));
                }
            })
            ->with(['residence.documents', 'residence.photos', 'photos', 'tarifs', 'equipements'])
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

    /** Catalogue sans dates : un tarif de référence, jamais une promesse de disponibilité. */
    public function catalogue(?string $commune = null, int $personnes = 1, array $equipements = [], int $limite = 60, array $types = []): Collection
    {
        $enAvant = MiseEnAvantService::residencesEnAvant();
        $resultats = Unite::query()
            ->where('active', true)->where('capacite', '>=', max(1, $personnes))
            ->whereHas('residence', function ($q) use ($commune) {
                $q->publiee();
                if ($commune) {
                    $q->where('commune', $commune);
                }
            })
            ->whereHas('tarifs', fn ($q) => $q->where('type_periode', 'standard'))
            ->when($types, fn ($q) => $q->whereIn('type', $types))
            ->when($equipements, function ($q) use ($equipements) {
                foreach ($equipements as $id) {
                    $q->whereHas('equipements', fn ($e) => $e->where('equipement.id', $id));
                }
            })
            ->with(['residence.documents', 'residence.photos', 'photos', 'tarifs', 'equipements'])
            ->limit($limite)->get()
            ->map(fn (Unite $u) => [
                'unite' => $u,
                'prix_reference' => $u->tarifs->firstWhere('type_periode', 'standard')->prix_nuit,
                'sponsorise' => in_array($u->residence_id, $enAvant, true),
            ]);

        return $resultats->where('sponsorise', true)->shuffle()
            ->concat($resultats->where('sponsorise', false)->sortBy('prix_reference'))->values();
    }

    /**
     * Nombre de logements présentés dans le catalogue, par commune (mêmes conditions que le catalogue sans dates).
     *
     * @return Collection<string, int>
     */
    public function logementsParCommune(): Collection
    {
        return Unite::query()
            ->where('active', true)
            ->whereHas('residence', fn ($q) => $q->publiee())
            ->whereHas('tarifs', fn ($q) => $q->where('type_periode', 'standard'))
            ->with('residence:id,commune')
            ->get()
            ->groupBy(fn (Unite $u) => $u->residence->commune)
            ->map(fn (Collection $l) => $l->count())
            ->sortKeys();
    }

    public function estLibre(Unite $unite, CarbonInterface $arrivee, CarbonInterface $depart): bool
    {
        return ! Unite::query()->whereKey($unite->getKey())
            ->whereExists(fn (Builder $q) => $this->occupation($q, $arrivee, $depart))
            ->exists();
    }

    /**
     * Prochaines dates libres pour le même nombre de nuits, à partir de la date demandée (au plus $nombre propositions,
     * qui ne se chevauchent pas). Une seule lecture des périodes occupées ; recherche limitée à 180 jours.
     *
     * @return list<array{arrivee: CarbonImmutable, depart: CarbonImmutable}>
     */
    public function prochainesDatesLibres(Unite $unite, CarbonInterface $apartirDe, int $nuits, int $nombre = 3): array
    {
        $debut = CarbonImmutable::parse($apartirDe)->startOfDay();
        $debut = $debut->lt(CarbonImmutable::today()) ? CarbonImmutable::today() : $debut;
        $fin = $debut->addDays(180);

        $occupees = collect(DB::select(
            'SELECT lower(periode) AS debut, upper(periode) AS fin FROM occupation_unite WHERE unite_id = ? AND upper(periode) > ? AND lower(periode) < ? ORDER BY lower(periode)',
            [$unite->id, $debut->toDateString(), $fin->addDays($nuits)->toDateString()],
        ))->map(fn ($p) => [CarbonImmutable::parse($p->debut), CarbonImmutable::parse($p->fin)]);

        $propositions = [];
        for ($jour = $debut; $jour->lt($fin) && count($propositions) < $nombre; $jour = $jour->addDay()) {
            $sortie = $jour->addDays($nuits);
            $gene = $occupees->first(fn (array $p) => $p[0]->lt($sortie) && $p[1]->gt($jour));
            if ($gene === null) {
                $propositions[] = ['arrivee' => $jour, 'depart' => $sortie];
                $jour = $sortie->subDay(); // la proposition suivante commence après celle-ci
            } else {
                $jour = $gene[1]->subDay(); // on saute directement à la fin de la période occupée
            }
        }

        return $propositions;
    }

    private function occupation(Builder $q, CarbonInterface $arrivee, CarbonInterface $depart): Builder
    {
        return $q->selectRaw('1')->from('occupation_unite')
            ->whereColumn('occupation_unite.unite_id', 'unite.id')
            ->whereRaw("occupation_unite.periode && daterange(?, ?, '[)')", [$arrivee->toDateString(), $depart->toDateString()]);
    }
}
