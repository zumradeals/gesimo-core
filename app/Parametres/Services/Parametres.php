<?php

namespace App\Parametres\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Parametres\Models\Parametre;
use App\Parametres\Models\ParametreHistorique;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Réglages de la plateforme, stockés en base (RG-20).
 * Les secrets sont chiffrés avec la clé maître (APP_KEY) avant d'être écrits : la base ne contient
 * que du texte chiffré. L'historique et le journal d'audit ne contiennent jamais la valeur d'un secret.
 */
class Parametres
{
    public const MASQUE = '••••••••';

    public static function obtenir(string $cle, ?string $defaut = null): ?string
    {
        $parametre = Parametre::where('cle', $cle)->first();

        if ($parametre === null || $parametre->valeur === null) {
            return $defaut;
        }

        return $parametre->est_secret ? Crypt::decryptString($parametre->valeur) : $parametre->valeur;
    }

    public static function definir(string $cle, ?string $valeur, ?Utilisateur $auteur = null, bool $secret = false, string $groupe = 'general'): Parametre
    {
        return DB::transaction(function () use ($cle, $valeur, $auteur, $secret, $groupe) {
            $parametre = Parametre::where('cle', $cle)->lockForUpdate()->first() ?? new Parametre(['cle' => $cle]);
            $ancien = $parametre->exists && ! $parametre->est_secret ? $parametre->valeur : null;

            // Une fois déclaré secret, un réglage ne redevient jamais lisible.
            $secret = $secret || $parametre->est_secret;

            $parametre->fill([
                'est_secret' => $secret,
                'groupe' => $groupe,
                'valeur' => $valeur !== null && $secret ? Crypt::encryptString($valeur) : $valeur,
                'modifie_par' => $auteur?->getKey(),
            ])->save();

            ParametreHistorique::create([
                'parametre_id' => $parametre->getKey(),
                'ancienne_valeur' => $secret ? null : $ancien,
                'nouvelle_valeur' => $secret ? null : $valeur,
                'est_secret' => $secret,
                'modifie_par' => $auteur?->getKey(),
            ]);

            Audit::enregistrer('parametre.modifie', $parametre, ['cle' => $cle, 'secret' => $secret], $auteur);

            return $parametre;
        });
    }

    /** Ce que l'écran Paramètres affiche : jamais un secret en clair. */
    public static function affichable(Parametre $parametre): ?string
    {
        if ($parametre->valeur === null) {
            return null;
        }

        return $parametre->est_secret ? self::MASQUE : $parametre->valeur;
    }
}
