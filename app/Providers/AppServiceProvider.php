<?php

namespace App\Providers;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Courriel;
use App\Noyau\Services\Sms\FournisseurSms;
use App\Noyau\Services\Sms\FournisseurSmsNonConfigure;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Paiement\Services\Fournisseur\GeniusPay;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Residence;
use App\Sejours\Policies\ReservationPolicy;
use App\Sejours\Policies\ResidencePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Aucun fournisseur SMS n'existe encore : le SMS reste désactivé tant qu'un vrai fournisseur n'est pas branché.
        $this->app->singleton(FournisseurSms::class, FournisseurSmsNonConfigure::class);
        // Le fournisseur de paiement est remplaçable : il suffit de changer cette ligne.
        $this->app->singleton(FournisseurPaiement::class, GeniusPay::class);
    }

    public function boot(): void
    {
        // Les tâches d'arrière-plan relisent les réglages e-mail avant chaque envoi (ils vivent en base).
        Queue::before(fn () => Courriel::appliquerLesReglages());

        Gate::policy(Residence::class, ResidencePolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);

        // Droits qui ne concernent pas un objet précis. Par défaut : refusé.
        Gate::define('modifier-parametres', fn (Utilisateur $u) => $u->estActif() && $u->estSuperAdmin());
        Gate::define('voir-journal-audit', fn (Utilisateur $u) => $u->estActif() && $u->estAdmin());
        Gate::define('voir-verifications', fn (Utilisateur $u) => $u->estActif() && $u->estAdmin());
        Gate::define('gerer-paiements', fn (Utilisateur $u) => $u->estActif() && $u->estAdmin());
        Gate::define('voir-comptabilite', fn (Utilisateur $u) => $u->estActif() && $u->estAdmin());
        Gate::define('enregistrer-reversement', fn (Utilisateur $u) => $u->estActif() && $u->estAdmin());
    }
}
