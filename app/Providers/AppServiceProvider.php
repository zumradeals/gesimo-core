<?php

namespace App\Providers;

use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\Reservation;
use App\Sejours\Models\Residence;
use App\Sejours\Policies\ReservationPolicy;
use App\Sejours\Policies\ResidencePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Residence::class, ResidencePolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);

        // Droits qui ne concernent pas un objet précis. Par défaut : refusé.
        Gate::define('modifier-parametres', fn (Utilisateur $u) => $u->estActif() && $u->estSuperAdmin());
        Gate::define('voir-journal-audit', fn (Utilisateur $u) => $u->estActif() && $u->estAdmin());
        Gate::define('voir-comptabilite', fn (Utilisateur $u) => $u->estActif() && $u->estAdmin());
        Gate::define('enregistrer-reversement', fn (Utilisateur $u) => $u->estActif() && $u->estAdmin());
    }
}
