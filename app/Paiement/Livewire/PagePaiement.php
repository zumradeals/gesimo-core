<?php

namespace App\Paiement\Livewire;

use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Paiement\Services\Fournisseur\RequeteRefusee;
use App\Paiement\Services\PaiementService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Paiement')]
class PagePaiement extends Component
{
    public int $reservationId;

    public ?string $erreur = null;

    public function mount(int $reservation): void
    {
        // Seulement MES réservations : celle d'une autre personne reste « introuvable ».
        $this->reservationId = Reservation::where('client_id', auth()->id())->findOrFail($reservation)->id;
    }

    public function payer(PaiementService $paiements)
    {
        $this->erreur = null;
        $reservation = $this->reservation();

        try {
            $paiement = $paiements->demarrer($reservation, auth()->user());
        } catch (PaiementImpossible $e) {
            $this->erreur = $e->getMessage();

            return null;
        } catch (AuthorizationException) {
            abort(403);
        } catch (FournisseurIndisponible|RequeteRefusee $e) {
            report($e);
            $this->erreur = 'Le paiement n\'a pas pu démarrer. Rien n\'a été débité. Réessayez dans un instant.';

            return null;
        }

        return redirect()->away($paiement->checkout_url);
    }

    public function render(PaiementService $paiements, FournisseurPaiement $fournisseur)
    {
        $reservation = $this->reservation()->load('unite.residence');
        if (in_array($reservation->statut, [StatutReservation::Confirmee, StatutReservation::ArriveeEnregistree, StatutReservation::Terminee], true)) {
            $this->redirectRoute('reservation.confirmation', $reservation);
        }

        return view('livewire.paiement.page-paiement', [
            'r' => $reservation,
            'enAttente' => $reservation->statut === StatutReservation::EnAttente && $reservation->expire_le?->isFuture(),
            'disponible' => $paiements->disponible(),
            'modeTest' => $fournisseur->mode() === 'sandbox',
            'resteAuGerant' => $reservation->montant_total - $reservation->acompte,
        ]);
    }

    private function reservation(): Reservation
    {
        return Reservation::where('client_id', auth()->id())->findOrFail($this->reservationId);
    }
}
