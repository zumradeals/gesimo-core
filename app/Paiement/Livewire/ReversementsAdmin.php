<?php

namespace App\Paiement\Livewire;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Format;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Models\Reversement;
use App\Paiement\Services\ReversementService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Reversements')]
class ReversementsAdmin extends Component
{
    public ?int $gerantId = null;

    public string $reference = '';

    public string $date = '';

    public ?string $erreur = null;

    public ?string $message = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('enregistrer-reversement'), 403);
        $this->date = now()->toDateString();
    }

    /** Un envoi Mobile Money couvre tous les séjours à reverser à ce gérant. */
    public function enregistrer(ReversementService $service): void
    {
        $this->erreur = $this->message = null;
        $this->validate(['gerantId' => 'required|integer', 'date' => 'required|date'], [], ['date' => 'La date de l\'envoi']);

        $lignes = Reversement::where('gerant_id', $this->gerantId)->where('statut', 'a_payer')->get();
        if ($lignes->isEmpty()) {
            $this->erreur = 'Rien à reverser à ce gérant.';

            return;
        }

        try {
            DB::transaction(function () use ($service, $lignes) {
                foreach ($lignes as $reversement) {
                    $service->enregistrer(auth()->user(), $reversement, $this->reference, CarbonImmutable::parse($this->date));
                }
            });
        } catch (PaiementImpossible $e) {
            $this->erreur = $e->getMessage();

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->message = 'Reversement enregistré : '.Format::fcfa((int) $lignes->sum('montant')).'.';
        $this->reference = '';
        $this->gerantId = null;
    }

    public function render()
    {
        $groupes = Reversement::where('statut', 'a_payer')->with('gerant', 'reservation.unite')->get()->groupBy('gerant_id');
        $this->gerantId ??= $groupes->keys()->first();
        $choisi = $groupes->get($this->gerantId, collect());

        return view('livewire.paiement.reversements-admin', [
            'groupes' => $groupes,
            'choisi' => $choisi,
            'gerant' => $this->gerantId ? Utilisateur::find($this->gerantId) : null,
            'historique' => Reversement::where('statut', 'paye')->with('gerant')->latest('paye_le')->limit(30)->get(),
        ]);
    }
}
