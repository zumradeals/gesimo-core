<?php

namespace App\Parametres\Console;

use App\Parametres\Services\Parametres;
use Illuminate\Console\Command;

/**
 * Enregistre un réglage depuis le serveur, en attendant l'écran Paramètres (Lot 5).
 * Une valeur secrète est demandée à la saisie (masquée) : elle n'apparaît ni dans la commande, ni dans l'historique du terminal.
 */
class DefinirParametre extends Command
{
    protected $signature = 'gesimo:parametre {cle : Ex. email.hote} {valeur? : Laisser vide pour un secret : elle sera demandée} {--secret : La valeur est un secret (chiffrée en base)}';

    protected $description = 'Enregistre un réglage de la plateforme (les secrets sont chiffrés en base)';

    public function handle(): int
    {
        $cle = (string) $this->argument('cle');
        $secret = (bool) $this->option('secret');

        if (! preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $cle)) {
            $this->error('Nom de réglage invalide. Exemple : email.hote');

            return self::FAILURE;
        }

        $valeur = $this->argument('valeur');
        if ($secret) {
            if ($valeur !== null) {
                $this->error('Ne tapez pas un secret dans la commande : laissez la valeur vide, elle sera demandée à la saisie.');

                return self::FAILURE;
            }
            $valeur = (string) $this->secret("Valeur de {$cle} (masquée)");
        }

        Parametres::definir($cle, $valeur === null ? null : (string) $valeur, null, $secret);
        $this->info($secret ? "Réglage {$cle} enregistré (chiffré)." : "Réglage {$cle} enregistré.");

        return self::SUCCESS;
    }
}
