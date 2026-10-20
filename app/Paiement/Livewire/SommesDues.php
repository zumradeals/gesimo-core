<?php

namespace App\Paiement\Livewire;

use App\Paiement\Models\EcritureComptable;
use App\Paiement\Models\Reversement;
use App\Paiement\Services\Comptabilite;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Ce que GESIMO doit au gérant : uniquement SES montants. */
#[Title('Argent')]
class SommesDues extends Component
{
    public function render()
    {
        $gerantId = auth()->id();
        $reservations = Reservation::query()
            ->whereHas('unite.residence', fn ($q) => $q->where('gerant_id', $gerantId))
            ->where('origine', 'plateforme')
            ->whereIn('statut', [StatutReservation::Confirmee->value, StatutReservation::ArriveeEnregistree->value, StatutReservation::Terminee->value, StatutReservation::Annulee->value, StatutReservation::Litige->value])
            ->whereHas('paiements', fn ($q) => $q->where('statut', 'reussi'))
            ->with('unite')->latest('date_arrivee')->limit(100)->get();

        $ids = $reservations->pluck('id');
        $ecritures = EcritureComptable::whereIn('reservation_id', $ids)->whereIn('type', ['encaissement', 'commission', 'part_gerant'])->get()->groupBy('reservation_id');
        $reversements = Reversement::where('gerant_id', $gerantId)->get()->keyBy('reservation_id');

        $lignes = $reservations->map(function (Reservation $r) use ($ecritures, $reversements) {
            $e = $ecritures->get($r->id, collect());
            $encaisse = (int) $e->where('type', 'encaissement')->sum('montant');
            $commission = (int) $e->where('type', 'commission')->sum('montant');
            $part = (int) $e->where('type', 'part_gerant')->sum('montant');
            $rev = $reversements->get($r->id);
            $prevue = $r->statut === StatutReservation::Confirmee ? $encaisse - Comptabilite::calculerCommission($r, $r->montant_total, $encaisse) : null;

            return compact('r', 'encaisse', 'commission', 'part', 'rev', 'prevue');
        });

        return view('livewire.paiement.sommes-dues', [
            'lignes' => $lignes,
            'aRecevoir' => (int) Reversement::where('gerant_id', $gerantId)->where('statut', 'a_payer')->sum('montant'),
            'dejaRecu' => (int) Reversement::where('gerant_id', $gerantId)->where('statut', 'paye')->sum('montant'),
            'commissions' => (int) DB::table('ecriture_comptable')->where('type', 'commission')
                ->whereIn('reservation_id', Reservation::whereHas('unite.residence', fn ($q) => $q->where('gerant_id', $gerantId))->select('id'))->sum('montant'),
            'aEncaisser' => (int) Reservation::whereHas('unite.residence', fn ($q) => $q->where('gerant_id', $gerantId))
                ->where('statut', StatutReservation::Confirmee->value)->sum('solde'),
        ]);
    }
}
