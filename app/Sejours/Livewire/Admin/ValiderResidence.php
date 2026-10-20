<?php

namespace App\Sejours\Livewire\Admin;

use App\Noyau\Services\Audit;
use App\Sejours\Models\Residence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Vérifier une résidence')]
class ValiderResidence extends Component
{
    public Residence $residence;

    public bool $badge = false;

    public string $motifRefus = '';

    public ?string $message = null;

    public function mount(Residence $residence): void
    {
        Gate::authorize('verifier', $residence);
        $this->residence = $residence;
        $this->badge = $residence->badge_verifie;
    }

    public function valider(): void
    {
        Gate::authorize('verifier', $this->residence);
        $this->resetErrorBag();

        DB::transaction(function () {
            $this->residence->update([
                'statut_verification' => 'verifiee',
                'badge_verifie' => $this->badge,
                'motif_refus' => null,
                'verifiee_par' => auth()->id(),
                'verifiee_le' => now(),
            ]);
            $this->residence->documents()->update(['statut' => 'accepte', 'verifie_par' => auth()->id(), 'verifie_le' => now()]);
            Audit::enregistrer('residence.validee', $this->residence, ['badge' => $this->badge]);
        });
        $this->message = 'Résidence validée et mise en ligne.';
    }

    public function refuser(): void
    {
        Gate::authorize('verifier', $this->residence);
        $this->validate(['motifRefus' => 'required|string|max:1000'], ['motifRefus.required' => 'Indiquez le motif du refus.']);

        DB::transaction(function () {
            $this->residence->update([
                'statut_verification' => 'refusee',
                'badge_verifie' => false,
                'motif_refus' => trim($this->motifRefus),
                'verifiee_par' => auth()->id(),
                'verifiee_le' => now(),
            ]);
            Audit::enregistrer('residence.refusee', $this->residence, ['motif' => trim($this->motifRefus)]);
        });
        $this->message = 'Résidence refusée. Le gérant verra le motif.';
    }

    public function render()
    {
        return view('livewire.admin.valider-residence', [
            'r' => $this->residence->fresh()->load(['gerant', 'unites.tarifs', 'photos', 'documents']),
        ]);
    }
}
