<?php

namespace App\Sejours\Livewire;

use App\Paiement\Services\AnnulationService;
use App\Sejours\Enums\HeureArriveePrevue;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Litige;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\PiecesVoyageur;
use App\Sejours\Services\ReservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ma réservation')]
class DetailReservation extends Component
{
    public int $reservationId;

    public bool $edition = false;

    public string $heureArrivee = 'inconnue';

    public string $message = '';

    public ?string $erreur = null;

    public ?string $info = null;

    public function mount(int $reservation): void
    {
        $r = Reservation::where('client_id', auth()->id())->findOrFail($reservation);
        $this->reservationId = $r->id;
        $this->heureArrivee = $r->heure_arrivee_prevue ?? 'inconnue';
        $this->message = (string) $r->message_gerant;
    }

    /** Le voyageur corrige l'heure d'arrivée prévue et son message, jusqu'à la veille de l'arrivée. */
    public function enregistrerInformations(ReservationService $service): void
    {
        $this->erreur = $this->info = null;
        $reservation = Reservation::where('client_id', auth()->id())->findOrFail($this->reservationId);

        try {
            $service->modifierInformations($reservation, auth()->user(), $this->heureArrivee, $this->message);
            $this->edition = false;
            $this->info = 'Vos informations sont enregistrées. Le gérant les verra.';
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();
        } catch (AuthorizationException) {
            $this->erreur = 'Vous ne pouvez pas modifier cette réservation.';
        }
    }

    /** Le voyageur vient d'ajouter sa pièce : la page se remet à jour. */
    #[On('piece-enregistree')]
    public function pieceEnregistree(): void {}

    public function render(AnnulationService $annulations)
    {
        $r = Reservation::with('unite.residence.gerant', 'unite.photos', 'unite.residence.photos')->findOrFail($this->reservationId);
        $apercu = $r->statut === StatutReservation::Confirmee ? $annulations->apercu($r, auth()->user()) : null;

        return view('livewire.sejours.detail-reservation', [
            'r' => $r,
            'paye' => (int) $r->paiements()->where('statut', 'reussi')->sum('montant'),
            'avis' => Avis::where('reservation_id', $r->id)->first(),
            'litigeOuvert' => Litige::where('reservation_id', $r->id)->where('ouvert_par', auth()->id())->where('statut', 'ouvert')->first(),
            'apercu' => $apercu,
            'pieceFournie' => PiecesVoyageur::pieceValide(auth()->user()) !== null,
            'modifiable' => ReservationService::informationsModifiables($r),
            'heures' => HeureArriveePrevue::choix(),
        ]);
    }
}
