<?php

namespace App\Sejours\Livewire\Admin;

use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Reservation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** F-A3 : toutes les réservations, en lecture seule. */
#[Title('Réservations')]
class ToutesLesReservations extends Component
{
    use WithPagination;

    #[Url]
    public string $statut = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('voir-comptabilite'), 403);
    }

    public function updatedStatut(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $filtre = StatutReservation::tryFrom($this->statut);

        return view('livewire.admin.toutes-les-reservations', [
            'reservations' => Reservation::with('unite.residence', 'client')->when($filtre, fn ($q) => $q->where('statut', $filtre->value))->latest('id')->paginate(25),
            'statuts' => StatutReservation::cases(),
        ]);
    }
}
