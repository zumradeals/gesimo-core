<?php

use App\Noyau\Console\CreerSuperAdmin;
use App\Noyau\Console\EnvoyerRappelsArrivee;
use App\Noyau\Http\Middleware\VerifierRole;
use App\Parametres\Console\DefinirParametre;
use App\Sejours\Console\ExpirerReservations;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CreerSuperAdmin::class,
        EnvoyerRappelsArrivee::class,
        ExpirerReservations::class,
        DefinirParametre::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Les messages de GeniusPay n'ont pas de jeton de formulaire : ils sont protégés par leur signature.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->redirectGuestsTo(fn () => route('connexion'));
        $middleware->redirectUsersTo(fn () => route('accueil'));
        $middleware->alias(['role' => VerifierRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
