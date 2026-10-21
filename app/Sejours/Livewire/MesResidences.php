<?php

namespace App\Sejours\Livewire;

use App\Sejours\Models\Residence;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mes résidences')]
class MesResidences extends Component
{
    public function render()
    {
        return view('livewire.sejours.mes-residences', [
            // Un gérant ne voit que SES résidences.
            'residences' => Residence::where('gerant_id', auth()->id())->with(['documents', 'gerant.profilGerant'])->withCount('unites')->latest('id')->get(),
        ]);
    }
}
