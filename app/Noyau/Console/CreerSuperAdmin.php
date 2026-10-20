<?php

namespace App\Noyau\Console;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Noyau\Services\Telephone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Crée le premier super-administrateur depuis le serveur (en attendant l'assistant d'installation).
 * Le mot de passe est demandé à la saisie : il n'est jamais passé en option ni écrit nulle part.
 */
class CreerSuperAdmin extends Command
{
    protected $signature = 'gesimo:creer-super-admin {--nom=} {--telephone=} {--email=}';

    protected $description = 'Crée un compte super-administrateur (le mot de passe est demandé à la saisie)';

    public function handle(): int
    {
        if (Utilisateur::whereHas('roles', fn ($q) => $q->where('role', Role::SuperAdmin->value))->exists()
            && ! $this->confirm('Un super-administrateur existe déjà. En créer un autre ?', false)) {
            return self::SUCCESS;
        }

        $nom = $this->option('nom') ?: $this->ask('Nom complet');
        $telephone = Telephone::normaliser((string) ($this->option('telephone') ?: $this->ask('Téléphone (10 chiffres)')));
        $email = mb_strtolower((string) ($this->option('email') ?: $this->ask('E-mail')));
        $motDePasse = (string) $this->secret('Mot de passe (8 caractères minimum)');
        $confirmation = (string) $this->secret('Retapez le mot de passe');

        $validation = Validator::make(
            ['nom' => $nom, 'telephone' => $telephone, 'email' => $email, 'mot_de_passe' => $motDePasse],
            [
                'nom' => 'required|string|max:120',
                'telephone' => ['required', 'regex:/^\d{10}$/', 'unique:utilisateur,telephone'],
                'email' => 'required|email|unique:utilisateur,email',
                'mot_de_passe' => 'required|string|min:8|max:100',
            ],
        );
        if ($validation->fails() || $motDePasse !== $confirmation) {
            foreach ($validation->errors()->all() as $erreur) {
                $this->error($erreur);
            }
            if ($motDePasse !== $confirmation) {
                $this->error('Les deux mots de passe ne sont pas identiques.');
            }

            return self::FAILURE;
        }

        $utilisateur = DB::transaction(function () use ($nom, $telephone, $email, $motDePasse) {
            $u = Utilisateur::create(['nom' => $nom, 'telephone' => $telephone, 'email' => $email, 'mot_de_passe' => $motDePasse]);
            $u->attribuerRole(Role::SuperAdmin);
            Audit::enregistrer('compte.super_admin_cree', $u, [], $u);

            return $u;
        });

        $this->info("Super-administrateur créé : {$utilisateur->nom} ({$utilisateur->email}).");

        return self::SUCCESS;
    }
}
