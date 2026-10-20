<?php

namespace App\Sejours\Livewire;

use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Avis;
use App\Sejours\Models\Reservation;
use App\Sejours\Services\AvisService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Votre avis')]
class DonnerAvis extends Component
{
    public int $reservationId;

    public int $note = 5;

    public string $commentaire = '';

    public ?string $erreur = null;

    public bool $fait = false;

    public function mount(int $reservation): void
    {
        $this->reservationId = Reservation::where('client_id', auth()->id())->findOrFail($reservation)->id;
        $this->fait = Avis::where('reservation_id', $this->reservationId)->exists();
    }

    public function choisir(int $note): void
    {
        $this->note = max(1, min(5, $note));
    }

    public function publier(AvisService $service): void
    {
        $this->erreur = null;
        $reservation = Reservation::where('client_id', auth()->id())->findOrFail($this->reservationId);
        try {
            $service->donner($reservation, auth()->user(), $this->note, $this->commentaire);
            $this->fait = true;
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();
        } catch (AuthorizationException) {
            abort(403);
        }
    }

    public function render()
    {
        return view('livewire.sejours.donner-avis', ['r' => Reservation::with('unite.residence')->findOrFail($this->reservationId)]);
    }
}
