<?php

namespace App\Sejours\Livewire\Admin;

use App\Noyau\Enums\Role;
use App\Noyau\Services\Audit;
use App\Sejours\Models\Residence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Vérifier une résidence')]
class ValiderResidence extends Component
{
    public Residence $residence;

    public bool $badge = false;

    public string $motifRefus = '';

    public string $motifGestion = '';

    public ?string $message = null;

    public function mount(Residence $residence): void
    {
        Gate::authorize('verifier', $residence);
        $this->residence = $residence;
        $this->badge = $residence->aBadgeGestion();
    }

    public function valider(): void
    {
        Gate::authorize('verifier', $this->residence);
        $this->resetErrorBag();
        DB::transaction(function () {
            $r = Residence::lockForUpdate()->findOrFail($this->residence->id);
            if (! $r->gerant->estActif() || ! $r->gerant->aRole(Role::Gerant) || ! $r->gerant->profilGerant?->identiteValidee()) {
                throw ValidationException::withMessages(['decision' => 'Validez d’abord la pièce d’identité dans le dossier du gérant.']);
            }
            $r->update(['statut_verification' => 'verifiee', 'badge_verifie' => false, 'motif_refus' => null, 'verifiee_par' => auth()->id(), 'verifiee_le' => now()]);
            if ($this->badge) {
                $this->accepterGestion($r);
            }
            Audit::enregistrer('residence.validee', $r, ['badge' => $this->badge]);
        });
        $this->residence->refresh();
        $this->message = 'Résidence validée et mise en ligne.';
    }

    public function accorderBadge(): void
    {
        Gate::authorize('verifier', $this->residence);
        $this->resetErrorBag();
        DB::transaction(function () {
            $r = Residence::lockForUpdate()->findOrFail($this->residence->id);
            if (! $r->estVerifiee()) {
                throw ValidationException::withMessages(['decision' => 'Validez l’annonce avant d’attribuer son badge.']);
            }
            $this->accepterGestion($r);
        });
        $this->residence->refresh();
        $this->badge = true;
        $this->message = 'Justificatif de gestion validé. Le badge concerne cette résidence uniquement.';
    }

    private function accepterGestion(Residence $r): void
    {
        $d = $r->documents()->where('type', 'justificatif_droit_gestion')->whereNull('remplace_le')->latest('id')->first();
        if (! $d || ! Storage::disk('local')->exists($d->chemin)) {
            throw ValidationException::withMessages(['decision' => 'Un justificatif de gestion disponible et contrôlé est nécessaire pour attribuer le badge.']);
        }
        $r->documents()->where('type', 'justificatif_droit_gestion')->where('id', '<>', $d->id)->whereNull('remplace_le')->update(['remplace_le' => now()]);
        $d->update(['statut' => 'accepte', 'motif_refus' => null, 'verifie_par' => auth()->id(), 'verifie_le' => now()]);
        $r->update(['badge_verifie' => true, 'gestion_verifiee_par' => auth()->id(), 'gestion_verifiee_le' => now()]);
        Audit::enregistrer('residence.gestion_verifiee', $r, ['document_id' => $d->id]);
    }

    public function refuserJustificatif(int $id): void
    {
        Gate::authorize('verifier', $this->residence);
        $this->validate(['motifGestion' => 'required|string|max:1000']);
        if (trim($this->motifGestion) === '') {
            throw ValidationException::withMessages(['motifGestion' => 'Indiquez le motif du refus.']);
        }
        DB::transaction(function () use ($id) {
            $r = Residence::lockForUpdate()->findOrFail($this->residence->id);
            $d = $r->documents()->where('type', 'justificatif_droit_gestion')->whereNull('remplace_le')->findOrFail($id);
            $d->update(['statut' => 'refuse', 'motif_refus' => trim($this->motifGestion), 'verifie_par' => auth()->id(), 'verifie_le' => now()]);
            if (! $r->documents()->where('type', 'justificatif_droit_gestion')->where('statut', 'accepte')->whereNull('remplace_le')->exists()) {
                $r->update(['badge_verifie' => false, 'gestion_verifiee_le' => null, 'gestion_verifiee_par' => null]);
            }
            Audit::enregistrer('residence.justificatif_refuse', $d);
        });
        $this->message = 'Justificatif refusé. Le statut de publication de l’annonce est conservé.';
    }

    public function refuser(): void
    {
        Gate::authorize('verifier', $this->residence);
        $this->validate(['motifRefus' => 'required|string|max:1000'], ['motifRefus.required' => 'Indiquez le motif du refus.']);
        if (trim($this->motifRefus) === '') {
            throw ValidationException::withMessages(['motifRefus' => 'Indiquez le motif du refus.']);
        }
        DB::transaction(function () {
            $this->residence->update(['statut_verification' => 'refusee', 'badge_verifie' => false, 'motif_refus' => trim($this->motifRefus), 'verifiee_par' => auth()->id(), 'verifiee_le' => now()]);
            Audit::enregistrer('residence.refusee', $this->residence, ['motif' => trim($this->motifRefus)]);
        });
        $this->message = 'Résidence refusée. Le gérant verra le motif.';
    }

    public function render()
    {
        Gate::authorize('verifier', $this->residence);

        return view('livewire.admin.valider-residence', ['r' => $this->residence->fresh()->load(['gerant.profilGerant', 'unites.tarifs', 'photos', 'documents'])]);
    }
}
