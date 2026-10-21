<?php

namespace App\Sejours\Livewire;

use App\Paiement\Services\MiseEnAvantService;
use App\Parametres\Services\Reglages;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Residence;
use App\Sejours\Models\Unite;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Page d'accueil du site. Rien n'y est inventé : logements, communes, nombres et avis viennent de la plateforme,
 * et une section sans contenu réel n'est pas affichée.
 */
#[Title('Résidences meublées en Côte d\'Ivoire')]
class Accueil extends Component
{
    public function render()
    {
        $enAvant = MiseEnAvantService::residencesEnAvant();

        $unites = Unite::query()
            ->where('active', true)
            ->whereHas('residence', fn ($q) => $q->where('statut_verification', 'verifiee'))
            ->whereHas('tarifs', fn ($q) => $q->where('type_periode', 'standard'))
            ->with(['residence.photos', 'photos', 'tarifs', 'equipements'])
            ->addSelect(['note_moyenne' => Avis::query()
                ->selectRaw('avg(avis.note)')
                ->join('reservation', 'reservation.id', '=', 'avis.reservation_id')
                ->whereColumn('reservation.unite_id', 'unite.id'),
            ])
            ->latest('id')->limit(30)->get()
            ->sortBy([
                fn ($a, $b) => (int) in_array($b->residence_id, $enAvant, true) <=> (int) in_array($a->residence_id, $enAvant, true),
                fn ($a, $b) => ((float) $b->note_moyenne) <=> ((float) $a->note_moyenne),
            ])
            ->take(3)->values();

        $communes = Residence::query()
            ->where('statut_verification', 'verifiee')
            ->join('unite', 'unite.residence_id', '=', 'residence.id')
            ->where('unite.active', true)
            ->select('residence.commune', DB::raw('count(unite.id) as nombre'))
            ->groupBy('residence.commune')
            ->orderByDesc('nombre')->orderBy('residence.commune')
            ->limit(6)->get();

        $avis = Avis::query()
            ->where('note', '>=', 4)->whereNotNull('commentaire')->whereRaw("trim(commentaire) <> ''")
            ->with(['client', 'reservation.unite.residence'])
            ->latest('id')->limit(3)->get();

        $blocage = Reglages::entier('reservation.duree_blocage_minutes');

        return view('livewire.sejours.accueil', [
            'unites' => $unites,
            'enAvant' => $enAvant,
            'communes' => $communes,
            'toutesCommunes' => Residence::where('statut_verification', 'verifiee')->distinct()->orderBy('commune')->pluck('commune'),
            'avis' => $avis,
            'delai' => Reglages::entier('annulation.delai_gratuit_heures'),
            'blocage' => $blocage % 60 === 0 ? ($blocage / 60).' heure'.($blocage >= 120 ? 's' : '') : $blocage.' minutes',
        ]);
    }
}
