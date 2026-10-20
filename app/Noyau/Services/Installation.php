<?php

namespace App\Noyau\Services;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Parametres\Services\Parametres;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Installation au premier lancement (RG-23). Pour qu'un inconnu ne puisse pas prendre le contrôle du site avant son
 * propriétaire, l'assistant demande un code d'installation, affiché une seule fois sur le serveur (gesimo:installation).
 * Une fois terminé, l'assistant ne peut plus jamais être relancé.
 */
class Installation
{
    public const TERMINEE = 'installation.terminee';

    public const CODE = 'installation.code_hash';

    public static function estTerminee(): bool
    {
        return Parametres::obtenir(self::TERMINEE) === '1';
    }

    public static function superAdminExiste(): bool
    {
        return Utilisateur::whereHas('roles', fn ($q) => $q->where('role', Role::SuperAdmin->value))->exists();
    }

    /** Crée un nouveau code et renvoie sa valeur en clair (à n'afficher qu'une fois). Seule l'empreinte est gardée. */
    public static function genererCode(): string
    {
        $code = implode('-', str_split(Str::upper(Str::random(12)), 4));
        Parametres::definir(self::CODE, Hash::make($code), null, true, 'installation');

        return $code;
    }

    public static function codeGenere(): bool
    {
        return Parametres::estDefini(self::CODE);
    }

    /** Vérifie le code saisi : 5 essais par quart d'heure et par adresse. */
    public static function verifierCode(string $saisi, string $ip): bool
    {
        $cle = 'installation-code:'.$ip;
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            throw ValidationException::withMessages(['code' => 'Trop d\'essais. Réessayez dans quelques minutes.']);
        }
        $empreinte = Parametres::obtenir(self::CODE);
        $saisi = Str::upper(trim($saisi));
        if ($empreinte === null || $saisi === '' || ! Hash::check($saisi, $empreinte)) {
            RateLimiter::hit($cle, 900);

            return false;
        }
        RateLimiter::clear($cle);

        return true;
    }

    public static function terminer(Utilisateur $auteur): void
    {
        DB::transaction(function () use ($auteur) {
            Parametres::definir(self::TERMINEE, '1', $auteur, false, 'installation');
            // Le code ne sert plus : on l'efface.
            Parametres::definir(self::CODE, null, $auteur, true, 'installation');
            Audit::enregistrer('installation.terminee', null, [], $auteur);
        });
    }
}
