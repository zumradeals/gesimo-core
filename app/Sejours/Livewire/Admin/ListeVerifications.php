<?php

namespace App\Sejours\Livewire\Admin;

use App\Sejours\Models\ProfilGerant;
use App\Sejours\Models\Residence;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Résidences à vérifier')]
class ListeVerifications extends Component
{
    public function mount(): void
    {
        abort_unless(Gate::allows('voir-verifications'), 403);
    }

    public function render()
    {
        return view('livewire.admin.liste-verifications', [
            'gerantsAControler' => ProfilGerant::where(fn ($q) => $q->where('reprise_a_revoir', true)->orWhereHas('documents', fn ($d) => $d->where('statut', 'en_attente')->whereNull('remplace_le')))->with('utilisateur')->orderBy('id')->get(),
            'justificatifsAControler' => Residence::whereHas('documents', fn ($d) => $d->where('type', 'justificatif_droit_gestion')->where('statut', 'en_attente')->whereNull('remplace_le'))->with('gerant')->get(),
            'aVerifier' => Residence::where('statut_verification', 'en_attente')->whereNotNull('soumise_le')->with('gerant')->orderBy('soumise_le')->get(),
            'recentes' => Residence::whereIn('statut_verification', ['verifiee', 'refusee', 'suspendue'])->with('gerant')->latest('verifiee_le')->limit(15)->get(),
        ]);
    }
}
