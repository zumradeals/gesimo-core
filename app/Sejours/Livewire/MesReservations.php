<?php

namespace App\Sejours\Livewire;

use App\Paiement\Models\Remboursement;
use App\Paiement\Services\AnnulationService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\PiecesVoyageur;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Mes réservations')]
class MesReservations extends Component
{
    #[Url]
    public string $onglet = 'avenir';

    public ?string $erreur = null;

    public function annuler(int $id, AnnulationService $service): void
    {
        $this->erreur = null;
        // Seulement parmi MES réservations : l'identifiant d'une autre personne donne « introuvable ».
        $reservation = Reservation::where('client_id', auth()->id())->with('unite.residence')->findOrFail($id);

        try {
            $service->annuler($reservation, auth()->user());
            session()->flash('message', 'Réservation annulée.');
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function render()
    {
        $mes = Reservation::where('client_id', auth()->id());
        $enCours = [StatutReservation::EnAttente->value, StatutReservation::Confirmee->value, StatutReservation::ArriveeEnregistree->value, StatutReservation::Litige->value];
        $avenir = (clone $mes)->whereIn('statut', $enCours)->whereDate('date_depart', '>=', now()->toDateString());

        $reservations = ($this->onglet === 'passees'
            ? (clone $mes)->whereNotIn('id', (clone $avenir)->select('id'))->latest('date_arrivee')
            : (clone $avenir)->orderBy('date_arrivee'))->with(['unite.photos', 'unite.residence.photos', 'unite.residence.gerant'])->get();
        $ids = $reservations->pluck('id');
        $pieceFournie = PiecesVoyageur::pieceValide(auth()->user()) !== null;
        $avis = Avis::whereIn('reservation_id', $ids)->get()->keyBy('reservation_id');

        return view('livewire.sejours.mes-reservations', [
            'reservations' => $reservations,
            'nbAVenir' => (clone $avenir)->count(),
            'nbPassees' => (clone $mes)->whereNotIn('id', (clone $avenir)->select('id'))->count(),
            'pieceFournie' => $pieceFournie,
            'avis' => $avis,
            'rembourse' => Remboursement::whereIn('reservation_id', $ids)->where('statut', 'effectue')->groupBy('reservation_id')
                ->selectRaw('reservation_id, sum(montant) as total')->pluck('total', 'reservation_id'),
            'aFaire' => $this->aFaire($reservations, $pieceFournie, $avis),
        ]);
    }

    /**
     * La première chose à faire, parmi celles que la plateforme connaît déjà : finir un paiement, ajouter la pièce
     * d'identité (séjours à venir) ; donner un avis (séjours passés).
     *
     * @return array{type: string, reservation: Reservation}|null
     */
    private function aFaire(Collection $reservations, bool $pieceFournie, Collection $avis): ?array
    {
        if ($this->onglet === 'passees') {
            $r = $reservations->first(fn (Reservation $r) => $r->statut === StatutReservation::Terminee && ! $avis->has($r->id));

            return $r ? ['type' => 'avis', 'reservation' => $r] : null;
        }
        $enAttente = $reservations->filter(fn (Reservation $r) => $r->statut === StatutReservation::EnAttente && $r->expire_le?->isFuture())->sortBy('expire_le')->first();
        if ($enAttente) {
            return ['type' => 'paiement', 'reservation' => $enAttente];
        }
        if (! $pieceFournie) {
            $r = $reservations->first(fn (Reservation $r) => $r->statut === StatutReservation::Confirmee && $r->origine === 'plateforme');

            return $r ? ['type' => 'piece', 'reservation' => $r] : null;
        }

        return null;
    }
}
