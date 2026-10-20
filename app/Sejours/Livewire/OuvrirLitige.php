<?php

namespace App\Sejours\Livewire;

use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\LitigeService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/** « Signaler un problème » : même écran pour le client et pour le gérant (le droit est vérifié côté serveur). */
#[Title('Signaler un problème')]
class OuvrirLitige extends Component
{
    public int $reservationId;

    public string $motif = '';

    public ?string $erreur = null;

    public bool $fait = false;

    public function mount(int $reservation): void
    {
        $r = Reservation::with('unite.residence')->findOrFail($reservation);
        abort_unless(auth()->user()->can('ouvrirLitige', $r), 404);
        $this->reservationId = $r->id;
    }

    public function envoyer(LitigeService $service): void
    {
        $this->erreur = null;
        $r = Reservation::with('unite.residence')->findOrFail($this->reservationId);
        try {
            $service->ouvrir($r, auth()->user(), $this->motif);
            $this->fait = true;
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();
        } catch (AuthorizationException) {
            abort(404);
        }
    }

    public function render()
    {
        $r = Reservation::with('unite.residence')->findOrFail($this->reservationId);
        $parLeClient = $r->client_id === auth()->id();

        return view('livewire.sejours.ouvrir-litige', [
            'r' => $r,
            'retour' => $parLeClient ? route('reservation.detail', $r) : route('gerant.reservations'),
        ]);
    }
}
