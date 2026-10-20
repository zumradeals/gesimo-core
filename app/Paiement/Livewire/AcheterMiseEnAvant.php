<?php

namespace App\Paiement\Livewire;

use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Paiement\Services\Fournisseur\RequeteRefusee;
use App\Paiement\Services\MiseEnAvantService;
use App\Paiement\Services\PaiementService;
use App\Sejours\Models\Residence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mettre en avant')]
class AcheterMiseEnAvant extends Component
{
    public ?int $residenceId = null;

    public int $offre = 0;

    public ?string $erreur = null;

    public function mount(): void
    {
        $this->residenceId = $this->residences()->first()?->id;
    }

    public function payer(MiseEnAvantService $service, PaiementService $paiements)
    {
        $this->erreur = null;
        $residence = $this->residences(false)->firstWhere('id', $this->residenceId);
        if ($residence === null) {
            $this->erreur = 'Choisissez une de vos résidences.';

            return null;
        }

        try {
            $mea = $service->creerAchat(auth()->user(), $residence, $this->offre);
            $paiement = $paiements->demarrerMiseEnAvant($mea->load('residence'), auth()->user());
        } catch (PaiementImpossible $e) {
            $this->erreur = $e->getMessage();

            return null;
        } catch (AuthorizationException) {
            abort(403);
        } catch (FournisseurIndisponible|RequeteRefusee $e) {
            report($e);
            $this->erreur = 'Le paiement n\'a pas pu démarrer. Rien n\'a été débité. Réessayez dans un instant.';

            return null;
        }

        return redirect()->away($paiement->checkout_url);
    }

    public function render(FournisseurPaiement $fournisseur)
    {
        $offres = MiseEnAvantService::offres();

        return view('livewire.paiement.acheter-mise-en-avant', [
            'residences' => $this->residences(false),
            'offres' => $offres,
            'disponible' => $fournisseur->estConfigure(),
            'modeTest' => $fournisseur->mode() === 'sandbox',
        ]);
    }

    /** @return Collection<int, Residence> */
    private function residences(bool $seulementVerifiees = false)
    {
        return Residence::where('gerant_id', auth()->id())->orderBy('nom')->get();
    }
}
