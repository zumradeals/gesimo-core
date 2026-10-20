<?php

namespace App\Paiement\Livewire;

use App\Paiement\Models\MiseEnAvant;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mes mises en avant')]
class MisesEnAvantGerant extends Component
{
    public function render()
    {
        return view('livewire.paiement.mises-en-avant-gerant', [
            'mises' => MiseEnAvant::whereHas('residence', fn ($q) => $q->where('gerant_id', auth()->id()))
                ->where('statut', '!=', 'en_attente_paiement')->with('residence')->latest('id')->get(),
            'enAttente' => MiseEnAvant::whereHas('residence', fn ($q) => $q->where('gerant_id', auth()->id()))->where('statut', 'en_attente_paiement')->where('created_at', '>', now()->subDay())->with('residence')->latest('id')->get(),
        ]);
    }
}
