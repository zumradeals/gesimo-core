<?php

namespace App\Paiement\Livewire;

use App\Paiement\Models\AlerteAdmin;
use App\Paiement\Models\Paiement;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\RequeteRefusee;
use App\Paiement\Services\PaiementService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Retour de la page de paiement. Cette page ne CONFIRME rien : elle demande au serveur de vérifier le paiement
 * auprès de GeniusPay, puis affiche le résultat. Aucune information du navigateur n'est crue.
 */
#[Title('Retour de paiement')]
class RetourPaiement extends Component
{
    public int $reservationId;

    public bool $fournisseurInjoignable = false;

    public function mount(int $reservation, PaiementService $paiements)
    {
        $this->reservationId = Reservation::where('client_id', auth()->id())->findOrFail($reservation)->id;

        return $this->actualiser($paiements);
    }

    /** Appelée au retour puis toutes les quelques secondes tant que le paiement est en attente. */
    public function actualiser(PaiementService $paiements)
    {
        $paiement = $this->dernierPaiement();
        $this->fournisseurInjoignable = false;

        if ($paiement !== null && $paiement->statut === 'en_attente') {
            try {
                $paiement = $paiements->verifier($paiement);
            } catch (FournisseurIndisponible|RequeteRefusee) {
                $this->fournisseurInjoignable = true;
            }
        }

        $reservation = $this->reservation();
        if ($paiement?->statut === 'reussi' && in_array($reservation->statut, [StatutReservation::Confirmee, StatutReservation::ArriveeEnregistree, StatutReservation::Terminee], true)) {
            return redirect()->route('reservation.confirmation', $reservation);
        }

        return null;
    }

    public function render()
    {
        $reservation = $this->reservation()->load('unite.residence');
        $paiement = $this->dernierPaiement();

        $etat = match (true) {
            $paiement === null => 'aucun',
            $paiement->statut === 'reussi' => 'rembourse', // payé mais réservation non confirmée : voir plus bas
            $paiement->statut === 'echoue' => 'echec',
            default => 'verification',
        };

        return view('livewire.paiement.retour-paiement', [
            'r' => $reservation,
            'paiement' => $paiement,
            'etat' => $etat,
            'peutReessayer' => $reservation->statut === StatutReservation::EnAttente && $reservation->expire_le?->isFuture(),
            'tardifRembourse' => $paiement && AlerteAdmin::where('paiement_id', $paiement->id)->where('type', 'paiement_tardif_rembourse')->exists(),
        ]);
    }

    private function dernierPaiement(): ?Paiement
    {
        return Paiement::where('reservation_id', $this->reservationId)->latest('id')->first();
    }

    private function reservation(): Reservation
    {
        return Reservation::where('client_id', auth()->id())->findOrFail($this->reservationId);
    }
}
