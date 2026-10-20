<?php

namespace App\Sejours\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Sejours\Exceptions\ReservationImpossible;
use App\Sejours\Models\BlocageCalendrier;
use App\Sejours\Models\Unite;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BlocageService
{
    public function __construct(private ReservationService $reservations) {}

    /** Bloque des dates ; « fin » est le dernier jour bloqué (inclus). */
    public function bloquer(Utilisateur $auteur, Unite $unite, CarbonInterface $debut, CarbonInterface $finIncluse, ?string $motif = null): BlocageCalendrier
    {
        $unite->loadMissing('residence');
        Gate::forUser($auteur)->authorize('gererCalendrier', $unite->residence);

        if ($finIncluse->copy()->startOfDay()->lt($debut->copy()->startOfDay())) {
            throw new ReservationImpossible('La date de fin doit être égale ou après la date de début.');
        }
        $fin = $finIncluse->copy()->addDay();

        try {
            $blocage = DB::transaction(fn () => BlocageCalendrier::create([
                'unite_id' => $unite->id,
                'date_debut' => $debut->toDateString(),
                'date_fin' => $fin->toDateString(),
                'motif' => $motif ?: null,
                'cree_par' => $auteur->id,
            ]));
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== ReservationService::CHEVAUCHEMENT) {
                throw $e;
            }
            $conflit = $this->reservations->periodeEnConflit($unite, $debut, $fin);
            throw new ReservationImpossible($conflit
                ? 'Impossible : ces dates sont déjà prises du '.$conflit['debut']->translatedFormat('j M').' au '.$conflit['fin']->subDay()->translatedFormat('j M').' sur ce logement.'
                : 'Impossible : ces dates ne sont pas libres.');
        }

        Audit::enregistrer('calendrier.dates_bloquees', $blocage, ['unite_id' => $unite->id], $auteur);

        return $blocage;
    }

    public function debloquer(Utilisateur $auteur, BlocageCalendrier $blocage): void
    {
        $unite = Unite::with('residence')->findOrFail($blocage->unite_id);
        Gate::forUser($auteur)->authorize('gererCalendrier', $unite->residence);

        $blocage->delete();
        Audit::enregistrer('calendrier.blocage_retire', null, ['unite_id' => $unite->id, 'blocage_id' => $blocage->id], $auteur);
    }
}
