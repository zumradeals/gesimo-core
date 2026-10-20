<?php

namespace App\Noyau\Services;

use App\Noyau\Models\JournalAudit;
use App\Noyau\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Journal d'audit : qui, quoi, quand, sur quel objet.
 * Les détails ne contiennent jamais de secret : les clés sensibles sont masquées d'office.
 */
class Audit
{
    private const MOTS_SENSIBLES = ['passe', 'password', 'secret', 'token', 'cle', 'key', 'piece_numero'];

    public static function enregistrer(string $action, ?Model $objet = null, array $details = [], ?Utilisateur $auteur = null): JournalAudit
    {
        $auteur ??= Auth::user();

        return JournalAudit::create([
            'utilisateur_id' => $auteur?->getKey(),
            'action' => $action,
            'objet_type' => $objet?->getTable(),
            'objet_id' => $objet?->getKey(),
            'details' => $details === [] ? null : self::masquer($details),
            'adresse_ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    public static function masquer(array $details): array
    {
        foreach ($details as $nom => $valeur) {
            if (is_array($valeur)) {
                $details[$nom] = self::masquer($valeur);
            } elseif (self::estSensible((string) $nom)) {
                $details[$nom] = '***';
            }
        }

        return $details;
    }

    private static function estSensible(string $nom): bool
    {
        $nom = mb_strtolower($nom);
        foreach (self::MOTS_SENSIBLES as $mot) {
            if (str_contains($nom, $mot)) {
                return true;
            }
        }

        return false;
    }
}
