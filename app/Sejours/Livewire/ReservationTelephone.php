<?php

namespace App\Sejours\Livewire;

use App\Noyau\Services\Telephone;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\Unite;
use App\Sejours\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Réservation par téléphone')]
class ReservationTelephone extends Component
{
    #[Url(as: 'unite')]
    public ?int $uniteId = null;

    public string $arrivee = '';

    public string $depart = '';

    public string $nomClient = '';

    public string $telephoneClient = '';

    public ?string $erreur = null;

    public function enregistrer(ReservationService $service)
    {
        $this->erreur = null;
        $this->validate([
            'uniteId' => 'required|integer',
            'arrivee' => 'required|date',
            'depart' => 'required|date',
            'nomClient' => 'required|string|max:150',
            'telephoneClient' => 'nullable|string|max:30',
        ], [
            'nomClient.required' => 'Le nom du client est obligatoire.',
        ], ['arrivee' => 'La date d\'arrivée', 'depart' => 'La date de départ', 'uniteId' => 'Le logement']);

        $unite = $this->unitesDuGerant()->firstWhere('id', $this->uniteId);
        abort_if($unite === null, 403);

        try {
            $service->creerHorsPlateforme(
                auth()->user(), $unite,
                CarbonImmutable::parse($this->arrivee), CarbonImmutable::parse($this->depart),
                $this->nomClient, $this->telephoneClient !== '' ? Telephone::normaliser($this->telephoneClient) : null,
            );
        } catch (ReservationImpossible $e) {
            $this->erreur = $e->getMessage();

            return null;
        } catch (AuthorizationException) {
            abort(403);
        }

        return redirect()->route('gerant.calendrier', ['residenceId' => $unite->residence_id, 'uniteId' => $unite->id, 'mois' => CarbonImmutable::parse($this->arrivee)->format('Y-m')])
            ->with('message', 'Réservation par téléphone enregistrée : les dates sont bloquées.');
    }

    public function render()
    {
        $unites = $this->unitesDuGerant();
        $this->uniteId = $unites->firstWhere('id', $this->uniteId)?->id ?? $unites->first()?->id;

        return view('livewire.sejours.reservation-telephone', ['unites' => $unites]);
    }

    private function unitesDuGerant()
    {
        return Unite::where('active', true)
            ->whereHas('residence', fn ($q) => $q->where('gerant_id', auth()->id()))
            ->with('residence')->orderBy('residence_id')->orderBy('id')->get();
    }
}
