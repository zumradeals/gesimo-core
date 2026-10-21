<?php

namespace App\Sejours\Livewire;

use App\Paiement\Services\MiseEnAvantService;
use App\Parametres\Services\Reglages;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Photo;
use App\Sejours\Models\Residence;
use App\Sejours\Models\Unite;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Les offres réservables, communes et avis viennent de la plateforme.
 * Aucun exemple fictif ne complète les annonces publiées.
 */
#[Title('Résidences meublées en Côte d\'Ivoire')]
class Accueil extends Component
{
    public function render()
    {
        $enAvant = MiseEnAvantService::residencesEnAvant();

        $catalogue = Unite::query()
            ->where('active', true)
            ->whereHas('residence', fn ($q) => $q->publiee())
            ->whereHas('tarifs', fn ($q) => $q->where('type_periode', 'standard'))
            ->with(['residence.documents', 'residence.photos', 'photos', 'tarifs', 'equipements'])
            ->addSelect(['note_moyenne' => Avis::query()
                ->selectRaw('avg(avis.note)')
                ->join('reservation', 'reservation.id', '=', 'avis.reservation_id')
                ->whereColumn('reservation.unite_id', 'unite.id'),
            ])
            ->orderByRaw('note_moyenne DESC NULLS LAST')->latest('id');

        $unites = (clone $catalogue)->whereIn('residence_id', $enAvant)->limit(6)->get();
        $ordinaires = (clone $catalogue)->whereNotIn('residence_id', $enAvant)->limit(6)->get();

        // Un aperçu réel par commune, sans charger toutes les photos du catalogue.
        $apercuCommune = Photo::query()->select('photo.id')
            ->join('residence as residence_apercu', 'residence_apercu.id', '=', 'photo.residence_id')
            ->whereColumn('residence_apercu.commune', 'residence.commune')
            ->whereIn('photo.residence_id', Residence::query()->publiee()
                ->whereHas('unites', fn ($q) => $q->where('active', true))->select('residence.id'))
            ->where(fn ($q) => $q->whereNull('photo.unite_id')
                ->orWhereIn('photo.unite_id', Unite::query()->where('active', true)->select('id')))
            ->orderBy('photo.ordre')->orderBy('photo.id')->limit(1);

        $communes = Residence::query()
            ->publiee()
            ->join('unite', 'unite.residence_id', '=', 'residence.id')
            ->where('unite.active', true)
            ->select('residence.commune', DB::raw('count(unite.id) as nombre'))
            ->selectSub($apercuCommune, 'photo_apercu_id')
            ->groupBy('residence.commune')
            ->orderByDesc('nombre')->orderBy('residence.commune')
            ->get();

        $photosCommunes = Photo::query()->whereIn('id', $communes->pluck('photo_apercu_id')->filter())->get()->keyBy('id');
        $communes->each(fn ($commune) => $commune->setRelation('photoApercu', $photosCommunes->get($commune->photo_apercu_id)));

        $avis = Avis::query()->whereHas('reservation.unite.residence', fn ($q) => $q->publiee())
            ->where('note', '>=', 4)->whereNotNull('commentaire')->whereRaw("trim(commentaire) <> ''")
            ->with(['client', 'reservation.unite.residence'])
            ->latest('id')->limit(3)->get();

        $blocage = Reglages::entier('reservation.duree_blocage_minutes');

        return view('livewire.sejours.accueil', [
            'unites' => $unites,
            'ordinaires' => $ordinaires,
            'enAvant' => $enAvant,
            'communes' => $communes,
            'toutesCommunes' => Residence::query()->publiee()->distinct()->orderBy('commune')->pluck('commune'),
            'avis' => $avis,
            'delai' => Reglages::entier('annulation.delai_gratuit_heures'),
            'blocage' => $blocage % 60 === 0 ? ($blocage / 60).' heure'.($blocage >= 120 ? 's' : '') : $blocage.' minutes',
        ]);
    }
}
