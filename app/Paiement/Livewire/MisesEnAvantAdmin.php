<?php

namespace App\Paiement\Livewire;

use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Models\MiseEnAvant;
use App\Paiement\Services\MiseEnAvantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** F-A6 : l'administrateur suit et peut suspendre une mise en avant. */
#[Title('Mises en avant')]
class MisesEnAvantAdmin extends Component
{
    #[Url]
    public string $filtre = 'en_cours';

    public ?int $suspension = null;

    public string $motif = '';

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('gerer-mises-en-avant'), 403);
    }

    public function demanderSuspension(int $id): void
    {
        $this->suspension = $id;
        $this->motif = '';
        $this->erreur = null;
    }

    public function suspendre(MiseEnAvantService $service): void
    {
        $this->message = $this->erreur = null;
        try {
            $service->suspendre(MiseEnAvant::findOrFail($this->suspension), auth()->user(), $this->motif);
            $this->message = 'Mise en avant suspendue : l\'annonce redevient normale tout de suite.';
            $this->suspension = null;
        } catch (PaiementImpossible $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function reprendre(int $id, MiseEnAvantService $service): void
    {
        $this->message = $this->erreur = null;
        try {
            $service->reprendre(MiseEnAvant::findOrFail($id), auth()->user());
            $this->message = 'Mise en avant reprise jusqu\'à sa date de fin.';
        } catch (PaiementImpossible $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function render()
    {
        $jour = now()->toDateString();
        $base = MiseEnAvant::with('residence.gerant')->where('statut', '!=', 'en_attente_paiement');
        $liste = match ($this->filtre) {
            'programmees' => (clone $base)->where('statut', 'active')->whereDate('date_debut', '>', $jour),
            'terminees' => (clone $base)->where(fn ($q) => $q->whereIn('statut', ['terminee', 'annulee'])->orWhere(fn ($w) => $w->where('statut', 'active')->whereDate('date_fin', '<', $jour))),
            'suspendues' => (clone $base)->where('statut', 'suspendue'),
            default => (clone $base)->enCours(),
        };

        return view('livewire.paiement.mises-en-avant-admin', [
            'mises' => $liste->latest('id')->limit(100)->get(),
            'compteurs' => [
                'en_cours' => (clone $base)->enCours()->count(),
                'programmees' => (clone $base)->where('statut', 'active')->whereDate('date_debut', '>', $jour)->count(),
                'suspendues' => (clone $base)->where('statut', 'suspendue')->count(),
            ],
            'revenus' => (int) DB::table('ecriture_comptable')->where('type', 'mise_en_avant')->sum('montant'),
        ]);
    }
}
