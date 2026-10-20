<?php

namespace App\Paiement\Livewire;

use App\Paiement\Models\EcritureComptable;
use App\Paiement\Models\Paiement;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Tout l'argent qui entre et chaque mouvement comptable (F-A3) : consultation seulement. */
#[Title('Paiements et écritures')]
class PaiementsEtEcritures extends Component
{
    use WithPagination;

    #[Url]
    public string $onglet = 'paiements';

    #[Url]
    public string $statut = '';

    #[Url]
    public string $type = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('voir-comptabilite'), 403);
    }

    public function updating($nom): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $paiements = Paiement::with('reservation.client')->when($this->statut !== '', fn ($q) => $q->where('statut', $this->statut))->latest('id');
        $ecritures = EcritureComptable::with('auteur', 'reservation')->when($this->type !== '', fn ($q) => $q->where('type', $this->type))->latest('id');

        return view('livewire.paiement.paiements-et-ecritures', [
            'paiements' => $this->onglet === 'paiements' ? $paiements->paginate(25) : null,
            'ecritures' => $this->onglet === 'ecritures' ? $ecritures->paginate(25) : null,
        ]);
    }
}
