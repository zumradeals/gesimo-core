<?php

namespace App\Paiement\Livewire;

use App\Noyau\Enums\Role;
use App\Paiement\Models\Reversement;
use App\Paiement\Services\AnnulationService;
use App\Paiement\Services\ArriveeService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Litige;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Residence;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Aujourd\'hui')]
class ArriveesDuJour extends Component
{
    #[Url]
    public ?int $residenceId = null;

    #[Url]
    public string $jour = '';

    /** @var array<int, array{type?: string, numero?: string}> Pièce d'identité saisie pour chaque arrivée. */
    public array $pieces = [];

    public ?string $erreur = null;

    public ?string $message = null;

    public function mount(): void
    {
        $this->jour = $this->jour !== '' ? $this->jour : now()->toDateString();
    }

    public function arrivee(int $id, ArriveeService $service): void
    {
        $type = (string) ($this->pieces[$id]['type'] ?? 'cni');
        $numero = trim((string) ($this->pieces[$id]['numero'] ?? ''));
        if (! in_array($type, ['cni', 'passeport', 'permis', 'autre'], true)) {
            $type = 'autre';
        }
        if (mb_strlen($numero) < 4 || mb_strlen($numero) > 40) {
            $this->message = null;
            $this->erreur = 'Indiquez le numéro de la pièce d\'identité du client (4 à 40 caractères).';

            return;
        }
        $this->agir(fn ($r) => $service->enregistrerArrivee($r, auth()->user(), $type, $numero), $id, 'Arrivée enregistrée. Votre part est maintenant « à reverser » par Avelis.');
        unset($this->pieces[$id]);
    }

    public function depart(int $id, ArriveeService $service): void
    {
        $this->agir(fn ($r) => $service->enregistrerDepart($r, auth()->user()), $id, 'Départ enregistré.');
    }

    public function absent(int $id, AnnulationService $service): void
    {
        $this->agir(fn ($r) => $service->declarerAbsent($r, auth()->user()), $id, 'Client déclaré absent : aucun remboursement, votre part est « à reverser ».');
    }

    /** RG-10 : si le gérant annule, le client est remboursé en totalité. */
    public function annulerParLeGerant(int $id, AnnulationService $service): void
    {
        $this->agir(fn ($r) => $service->annuler($r, auth()->user()), $id, 'Réservation annulée : le client est remboursé en totalité.');
    }

    public function render()
    {
        $residences = $this->residencesAccessibles();
        if ($residences->isEmpty()) {
            return view('livewire.paiement.arrivees-du-jour', ['residences' => $residences, 'residence' => null]);
        }
        $residence = $residences->firstWhere('id', $this->residenceId) ?? $residences->first();
        $this->residenceId = $residence->id;
        $jour = $this->jourChoisi();
        $unites = $residence->unites()->pluck('id');

        $base = Reservation::whereIn('unite_id', $unites)->with(['unite', 'client'])->orderBy('date_arrivee');

        $estGerant = auth()->user()->aRole(Role::Gerant);
        $ids = $residences->pluck('id');
        $tous = Reservation::whereHas('unite', fn ($q) => $q->whereIn('residence_id', $ids));

        return view('livewire.paiement.arrivees-du-jour', [
            'residences' => $residences,
            'residence' => $residence,
            'jourDate' => $jour,
            'nbAVenir' => (clone $tous)->where('statut', StatutReservation::Confirmee->value)->where('date_arrivee', '>', $jour->toDateString())->where('date_arrivee', '<=', $jour->addDays(7)->toDateString())->count(),
            'sommeDue' => $estGerant ? (int) Reversement::where('gerant_id', auth()->id())->where('statut', 'a_payer')->sum('montant') : null,
            'litiges' => Litige::where('statut', 'ouvert')->where(fn ($q) => $q->where('ouvert_par', auth()->id())->orWhere('partie_adverse_id', auth()->id()))->count(),
            'attendues' => (clone $base)->whereIn('statut', [StatutReservation::Confirmee->value, StatutReservation::EnAttente->value])
                ->where('origine', 'plateforme')->where('date_arrivee', '<=', $jour->toDateString())->where('date_depart', '>', $jour->toDateString())
                ->where(fn ($q) => $q->where('statut', StatutReservation::Confirmee->value)->orWhere('date_arrivee', $jour->toDateString()))->get(),
            'presents' => (clone $base)->where('statut', StatutReservation::ArriveeEnregistree->value)->get(),
        ]);
    }

    /** @param callable(Reservation): mixed $action */
    private function agir(callable $action, int $id, string $succes): void
    {
        $this->erreur = $this->message = null;
        $ids = $this->residencesAccessibles()->pluck('id');
        $reservation = Reservation::whereHas('unite', fn ($q) => $q->whereIn('residence_id', $ids))->with('unite.residence')->findOrFail($id);

        try {
            $action($reservation);
            $this->message = $succes;
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();
        } catch (AuthorizationException) {
            $this->erreur = 'Vous n\'avez pas le droit de faire cette action pour cette résidence.';
        }
    }

    /** @return Collection<int, Residence> Le gérant : ses résidences ; le personnel : celles où il a le droit « arrivées et départs ». */
    private function residencesAccessibles(): Collection
    {
        $id = auth()->id();

        return Residence::query()
            ->where(fn ($q) => $q->where('gerant_id', $id)
                ->orWhereIn('id', MembreResidence::where('utilisateur_id', $id)->where('peut_gerer_checkin_checkout', true)->select('residence_id')))
            ->orderBy('nom')->get();
    }

    private function jourChoisi(): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->jour) ? CarbonImmutable::parse($this->jour)->startOfDay() : CarbonImmutable::now()->startOfDay();
    }
}
