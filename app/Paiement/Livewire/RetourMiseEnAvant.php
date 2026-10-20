<?php

namespace App\Paiement\Livewire;

use App\Paiement\Models\MiseEnAvant;
use App\Paiement\Models\Paiement;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\RequeteRefusee;
use App\Paiement\Services\PaiementService;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Retour de la page de paiement d'une mise en avant : le serveur vérifie auprès de GeniusPay, le navigateur n'est pas cru. */
#[Title('Retour de paiement')]
class RetourMiseEnAvant extends Component
{
    public int $miseId;

    public bool $fournisseurInjoignable = false;

    public function mount(int $miseEnAvant, PaiementService $paiements): void
    {
        $this->miseId = MiseEnAvant::whereHas('residence', fn ($q) => $q->where('gerant_id', auth()->id()))->findOrFail($miseEnAvant)->id;
        $this->actualiser($paiements);
    }

    public function actualiser(PaiementService $paiements): void
    {
        $this->fournisseurInjoignable = false;
        $paiement = Paiement::where('mise_en_avant_id', $this->miseId)->latest('id')->first();
        if ($paiement !== null && $paiement->statut === 'en_attente') {
            try {
                $paiements->verifier($paiement);
            } catch (FournisseurIndisponible|RequeteRefusee) {
                $this->fournisseurInjoignable = true;
            }
        }
    }

    public function render()
    {
        $mise = $this->mise()->load('residence');
        $paiement = Paiement::where('mise_en_avant_id', $mise->id)->latest('id')->first();
        $etat = match (true) {
            $paiement === null => 'aucun',
            $paiement->statut === 'reussi' && in_array($mise->statut, ['active', 'suspendue', 'terminee'], true) => 'ok',
            $paiement->statut === 'echoue' => 'echec',
            default => 'verification',
        };

        return view('livewire.paiement.retour-mise-en-avant', ['m' => $mise, 'etat' => $etat]);
    }

    private function mise(): MiseEnAvant
    {
        return MiseEnAvant::whereHas('residence', fn ($q) => $q->where('gerant_id', auth()->id()))->findOrFail($this->miseId);
    }
}
