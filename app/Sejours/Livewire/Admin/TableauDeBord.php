<?php

namespace App\Sejours\Livewire\Admin;

use App\Paiement\Models\AlerteAdmin;
use App\Paiement\Models\EcritureComptable;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Models\Litige;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Residence;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Ce qui demande l'équipe GESIMO, et les indicateurs du cahier (section 9). */
#[Title('Tableau de bord')]
class TableauDeBord extends Component
{
    public function mount(): void
    {
        abort_unless(Gate::allows('voir-verifications'), 403);
    }

    public function render()
    {
        $ligne = fn () => Reservation::where('origine', 'plateforme')->where('created_at', '>=', now()->subDays(30));
        $enLigne = max(1, $ligne()->count());
        $semaine = now()->subDays(7);

        return view('livewire.admin.tableau-de-bord', [
            'aValider' => Residence::where('statut_verification', 'en_attente')->whereNotNull('soumise_le')->orderBy('soumise_le')->limit(5)->get(),
            'nbAValider' => Residence::where('statut_verification', 'en_attente')->whereNotNull('soumise_le')->count(),
            'litiges' => Litige::where('statut', 'ouvert')->with('reservation.unite')->orderBy('id')->limit(5)->get(),
            'nbLitiges' => Litige::where('statut', 'ouvert')->count(),
            'alertes' => AlerteAdmin::whereNull('traitee_le')->orderBy('id')->limit(5)->get(),
            'nbAlertes' => AlerteAdmin::whereNull('traitee_le')->count(),
            'aujourdhui' => Reservation::where('origine', 'plateforme')->whereDate('created_at', now()->toDateString())->count(),
            'confirmeesSemaine' => Reservation::where('origine', 'plateforme')->where('created_at', '>=', $semaine)->whereIn('statut', [StatutReservation::Confirmee->value, StatutReservation::ArriveeEnregistree->value, StatutReservation::Terminee->value])->count(),
            'tauxExpiration' => (int) round(100 * $ligne()->where('statut', StatutReservation::Expiree->value)->count() / $enLigne),
            'tauxAnnulation' => (int) round(100 * $ligne()->where('statut', StatutReservation::Annulee->value)->count() / $enLigne),
            'commissionsSemaine' => (int) EcritureComptable::where('type', 'commission')->where('created_at', '>=', $semaine)->sum('montant'),
        ]);
    }
}
