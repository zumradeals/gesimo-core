<?php

namespace App\Noyau\Services;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;

/** Menus selon le rôle : barre du haut sur ordinateur, barre d'onglets en bas sur téléphone. */
class Navigation
{
    /** @return list<array{libelle: string, icone: string, route: string, actif: list<string>}> */
    public static function menu(?Utilisateur $u): array
    {
        $chercher = ['libelle' => 'Chercher', 'icone' => 'recherche', 'route' => 'accueil', 'actif' => ['accueil', 'recherche', 'logement', 'reserver']];

        if ($u === null) {
            return [$chercher, ['libelle' => 'Se connecter', 'icone' => 'compte', 'route' => 'connexion', 'actif' => ['connexion', 'inscription', 'connexion.lien']]];
        }

        $menu = [];
        if ($u->estAdmin()) {
            $menu[] = ['libelle' => 'À vérifier', 'icone' => 'verifie', 'route' => 'admin.residences', 'actif' => ['admin.residences', 'admin.residence']];
        }
        if ($u->aRole(Role::Gerant)) {
            $menu[] = ['libelle' => 'Calendrier', 'icone' => 'calendrier', 'route' => 'gerant.calendrier', 'actif' => ['gerant.calendrier', 'gerant.telephone']];
            $menu[] = ['libelle' => 'Résidences', 'icone' => 'batiment', 'route' => 'gerant.residences', 'actif' => ['gerant.residences', 'gerant.residence.nouvelle', 'gerant.residence']];
            $menu[] = ['libelle' => 'Personnel', 'icone' => 'equipe', 'route' => 'gerant.personnel', 'actif' => ['gerant.personnel']];
        }
        if ($u->aRole(Role::Client)) {
            $menu[] = $chercher;
            $menu[] = ['libelle' => 'Mes réservations', 'icone' => 'liste', 'route' => 'mes-reservations', 'actif' => ['mes-reservations']];
        }
        $menu[] = ['libelle' => 'Mon compte', 'icone' => 'compte', 'route' => 'compte', 'actif' => ['compte']];

        return $menu;
    }
}
