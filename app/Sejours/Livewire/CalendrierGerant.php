<?php

namespace App\Sejours\Livewire;

use App\Paiement\Services\AnnulationService;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\BlocageCalendrier;
use App\Sejours\Models\MembreResidence;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Residence;
use App\Sejours\Models\Unite;
use App\Sejours\Services\BlocageService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Calendrier')]
class CalendrierGerant extends Component
{
    #[Url]
    public ?int $residenceId = null;

    #[Url]
    public ?int $uniteId = null;

    #[Url]
    public string $mois = '';

    public string $debut = '';

    public string $fin = '';

    public string $motif = '';

    public ?string $erreur = null;

    public ?string $message = null;

    public function mount(): void
    {
        $this->mois = $this->mois !== '' ? $this->mois : now()->format('Y-m');
    }

    public function moisPrecedent(): void
    {
        $this->mois = $this->premierJour()->subMonth()->format('Y-m');
    }

    public function moisSuivant(): void
    {
        $this->mois = $this->premierJour()->addMonth()->format('Y-m');
    }

    public function bloquer(BlocageService $service): void
    {
        $this->reinitialiser();
        $this->validate([
            'debut' => 'required|date',
            'fin' => 'required|date',
            'motif' => 'nullable|string|max:190',
        ], [], ['debut' => 'La date de début', 'fin' => 'La date de fin']);

        $unite = $this->uniteChoisie();
        try {
            $service->bloquer(auth()->user(), $unite, CarbonImmutable::parse($this->debut), CarbonImmutable::parse($this->fin), $this->motif ?: null);
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->message = 'Dates bloquées.';
        $this->debut = $this->fin = $this->motif = '';
    }

    public function debloquer(int $id, BlocageService $service): void
    {
        $this->reinitialiser();
        $blocage = BlocageCalendrier::where('unite_id', $this->uniteChoisie()->id)->findOrFail($id);
        $service->debloquer(auth()->user(), $blocage);
        $this->message = 'Blocage retiré.';
    }

    public function annulerHorsPlateforme(int $id, AnnulationService $service): void
    {
        $this->reinitialiser();
        $reservation = Reservation::where('unite_id', $this->uniteChoisie()->id)->where('origine', 'hors_plateforme')->with('unite.residence')->findOrFail($id);
        try {
            $service->annuler($reservation, auth()->user());
            $this->message = 'Réservation annulée.';
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();
        } catch (AuthorizationException) {
            abort(403);
        }
    }

    public function render()
    {
        $residences = $this->residencesAccessibles();
        if ($residences->isEmpty()) {
            return view('livewire.sejours.calendrier-gerant', ['residences' => $residences, 'unites' => collect(), 'unite' => null]);
        }

        $unite = $this->uniteChoisie();
        $debutMois = $this->premierJour();
        $finMois = $debutMois->endOfMonth()->startOfDay();

        $reservations = Reservation::where('unite_id', $unite->id)
            ->whereIn('statut', array_map(fn ($s) => $s->value, array_filter(StatutReservation::cases(), fn ($s) => $s->occupeLeCalendrier())))
            ->where('date_arrivee', '<=', $finMois->toDateString())->where('date_depart', '>', $debutMois->toDateString())
            ->with('client')->orderBy('date_arrivee')->get();
        $blocages = BlocageCalendrier::where('unite_id', $unite->id)
            ->where('date_debut', '<=', $finMois->toDateString())->where('date_fin', '>', $debutMois->toDateString())->orderBy('date_debut')->get();

        // Une case par nuit occupée.
        $nuits = [];
        foreach ($blocages as $b) {
            for ($j = CarbonImmutable::parse($b->date_debut); $j->lt($b->date_fin); $j = $j->addDay()) {
                $nuits[$j->toDateString()] = ['type' => 'bloque', 'libelle' => 'Bloqué'];
            }
        }
        foreach ($reservations as $r) {
            for ($j = CarbonImmutable::parse($r->date_arrivee); $j->lt($r->date_depart); $j = $j->addDay()) {
                $nuits[$j->toDateString()] = match ($r->statut) {
                    StatutReservation::HorsPlateforme => ['type' => 'tel', 'libelle' => 'Tél.'],
                    StatutReservation::EnAttente => ['type' => 'attente', 'libelle' => 'En att.'],
                    default => ['type' => 'confirmee', 'libelle' => 'Confirmée'],
                };
            }
        }

        return view('livewire.sejours.calendrier-gerant', [
            'residences' => $residences,
            'unites' => $residences->firstWhere('id', $this->residenceId)->unites,
            'unite' => $unite,
            'debutMois' => $debutMois,
            'decalage' => $debutMois->dayOfWeekIso - 1,
            'nuits' => $nuits,
            'reservations' => $reservations,
            'blocages' => $blocages,
            'peutSaisirTelephone' => auth()->user()->can('creerHorsPlateforme', [Reservation::class, $unite]),
        ]);
    }

    /** @return Collection<int, Residence> */
    private function residencesAccessibles(): Collection
    {
        $id = auth()->id();

        return Residence::query()
            ->where(fn ($q) => $q->where('gerant_id', $id)
                ->orWhereIn('id', MembreResidence::where('utilisateur_id', $id)->where('peut_gerer_calendrier', true)->select('residence_id')))
            ->with(['unites' => fn ($q) => $q->where('active', true)->orderBy('id')])
            ->orderBy('nom')->get();
    }

    private function uniteChoisie(): Unite
    {
        $residences = $this->residencesAccessibles();
        abort_if($residences->isEmpty(), 403);

        // Une adresse trafiquée avec la résidence d'un autre ne donne jamais accès : on revient à la première.
        $residence = $residences->firstWhere('id', $this->residenceId) ?? $residences->first();
        $this->residenceId = $residence->id;
        $unite = $residence->unites->firstWhere('id', $this->uniteId) ?? $residence->unites->first();
        abort_if($unite === null, 404, 'Cette résidence n\'a pas encore de logement.');
        $this->uniteId = $unite->id;
        $unite->setRelation('residence', $residence);

        return $unite;
    }

    private function premierJour(): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}$/', $this->mois) ? CarbonImmutable::createFromFormat('!Y-m', $this->mois) : CarbonImmutable::now()->startOfMonth();
    }

    private function reinitialiser(): void
    {
        $this->erreur = null;
        $this->message = null;
        $this->resetErrorBag();
    }
}
