<?php

namespace App\Noyau\Console;

use App\Noyau\Services\Installation;
use Illuminate\Console\Command;

/** Affiche (une seule fois) le code d'installation à saisir dans l'assistant du premier lancement. */
class GenererCodeInstallation extends Command
{
    protected $signature = 'gesimo:installation';

    protected $description = 'Génère le code d\'installation demandé par l\'assistant du premier lancement';

    public function handle(): int
    {
        if (Installation::estTerminee()) {
            $this->error('L\'installation est déjà terminée : l\'assistant ne peut plus être relancé.');

            return self::FAILURE;
        }
        if (Installation::superAdminExiste()) {
            $this->info('Un super-administrateur existe déjà : connectez-vous puis ouvrez /installation. Aucun code n\'est nécessaire.');

            return self::SUCCESS;
        }

        $code = Installation::genererCode();
        $this->newLine();
        $this->info('Code d\'installation (affiché une seule fois, notez-le) :');
        $this->line("    {$code}");
        $this->newLine();
        $this->line('Ouvrez ensuite https://VOTRE-SITE/installation et saisissez ce code.');

        return self::SUCCESS;
    }
}
