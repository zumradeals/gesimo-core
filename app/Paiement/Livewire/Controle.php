<?php

namespace App\Paiement\Livewire;

use App\Noyau\Services\Audit;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Models\AlerteAdmin;
use App\Paiement\Models\EvenementPaiement;
use App\Paiement\Models\Paiement;
use App\Paiement\Models\Remboursement;
use App\Paiement\Services\Comptabilite;
use App\Paiement\Services\Rapprochement;
use App\Paiement\Services\RemboursementService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Rapprochement paiements / écritures et liste « À traiter ». */
#[Title('Contrôle')]
class Controle extends Component
{
    /** @var array<int, string> */
    public array $references = [];

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('gerer-paiements'), 403);
    }

    public function relancer(int $remboursementId, RemboursementService $service): void
    {
        $this->reinitialiser();
        $r = $service->relancer(Remboursement::findOrFail($remboursementId), auth()->user());
        $this->message = $r->statut === 'effectue' ? 'Remboursement effectué.' : 'Le remboursement n\'a pas encore pu être effectué : '.($r->derniere_erreur ?? 'réessayez plus tard.');
    }

    public function faitMain(int $remboursementId, RemboursementService $service): void
    {
        $this->reinitialiser();
        try {
            $service->marquerFaitMain(Remboursement::findOrFail($remboursementId), auth()->user(), $this->references[$remboursementId] ?? '');
            $this->message = 'Remboursement enregistré.';
        } catch (PaiementImpossible $e) {
            $this->erreur = $e->getMessage();
        }
    }

    public function ecrireEncaissement(int $paiementId): void
    {
        $this->reinitialiser();
        Gate::authorize('gerer-paiements');
        $paiement = Paiement::where('statut', 'reussi')->findOrFail($paiementId);
        Comptabilite::encaissement($paiement, auth()->user());
        Audit::enregistrer('comptabilite.encaissement_corrige', $paiement, [], auth()->user());
        $this->message = 'Écriture d\'encaissement créée.';
    }

    public function ecrireRemboursement(int $remboursementId): void
    {
        $this->reinitialiser();
        Gate::authorize('gerer-paiements');
        $remboursement = Remboursement::where('statut', 'effectue')->findOrFail($remboursementId);
        Comptabilite::remboursement($remboursement, auth()->user());
        Audit::enregistrer('comptabilite.remboursement_corrige', $remboursement, [], auth()->user());
        $this->message = 'Écriture de remboursement créée.';
    }

    public function traiter(int $alerteId): void
    {
        $this->reinitialiser();
        Gate::authorize('gerer-paiements');
        AlerteAdmin::whereNull('traitee_le')->whereKey($alerteId)->update(['traitee_le' => now(), 'traitee_par' => auth()->id()]);
    }

    public function render(Rapprochement $rapprochement)
    {
        return view('livewire.paiement.controle', [
            'bilan' => $rapprochement->calculer(),
            'alertes' => AlerteAdmin::whereNull('traitee_le')->with('reservation')->latest('id')->get(),
            'enEchec' => Remboursement::where('statut', 'en_attente')->whereNotNull('derniere_erreur')->get()->keyBy('id'),
            'refusesRecents' => EvenementPaiement::where('signature_valide', false)->where('recu_le', '>', now()->subDays(7))->count(),
        ]);
    }

    private function reinitialiser(): void
    {
        $this->message = $this->erreur = null;
    }
}
