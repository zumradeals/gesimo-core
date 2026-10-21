<?php

namespace App\Noyau\Services;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;

/** Menus : site public (en-tête) et espaces connectés (menu latéral sur ordinateur, menu du bas sur téléphone). */
class Navigation
{
    /** Pages publiques qui occupent toute la largeur (elles dessinent elles-mêmes leurs sections). */
    public const PAGES_PLEINES = ['accueil', 'recherche', 'logement', 'hebergeur', 'a-propos', 'devenir-partenaire', 'faq', 'contact', 'conditions', 'confidentialite', 'mentions-legales', 'connexion', 'inscription', 'connexion.lien', 'invitation'];

    /** Pages d'un compte (client, gérant) qui gardent le site public autour d'elles. */
    public const PAGES_PUBLIQUES = ['accueil', 'recherche', 'logement', 'hebergeur', 'a-propos', 'devenir-partenaire', 'faq', 'contact', 'conditions', 'confidentialite', 'mentions-legales', 'connexion', 'inscription', 'connexion.lien', 'invitation'];

    /**
     * Habillage de la page : « site » (visiteur et pages publiques), « admin », « gerant » (gérant et personnel)
     * ou « client » : chaque espace connecté a son menu latéral et n'affiche pas le site public autour de lui.
     */
    public static function habillage(?Utilisateur $u, ?string $route): string
    {
        $route ??= '';
        if ($u === null || in_array($route, self::PAGES_PUBLIQUES, true)) {
            return 'site';
        }
        $gerant = $u->aRole(Role::Gerant) || $u->aRole(Role::Personnel);
        if ($gerant && str_starts_with($route, 'gerant.')) {
            return 'gerant';
        }
        if ($u->estAdmin()) {
            return 'admin';
        }
        if ($gerant) {
            return 'gerant';
        }

        return 'client';
    }

    /**
     * Tout ce qu'il faut pour dessiner un espace connecté : menu latéral, quatre entrées du menu du bas (téléphone)
     * et le reste derrière « Plus ».
     *
     * @return array{menu: list<array{libelle: string, icone: string, route: string, actif: list<string>}>, bas: list<string>, libelles: array<string, string>, role: string}
     */
    public static function coque(Utilisateur $u, string $habillage): array
    {
        if ($habillage === 'admin') {
            return ['menu' => self::admin($u), 'bas' => self::ADMIN_BAS, 'libelles' => ['admin.tableau' => 'Accueil', 'admin.paiements' => 'Argent'], 'role' => $u->estSuperAdmin() ? 'Super-administrateur' : 'Administrateur'];
        }
        if ($habillage === 'gerant') {
            return ['menu' => self::gerant($u), 'bas' => $u->aRole(Role::Gerant) ? ['gerant.arrivees', 'gerant.calendrier', 'gerant.argent', 'gerant.residences'] : ['gerant.arrivees', 'gerant.calendrier'], 'libelles' => ['gerant.arrivees' => 'Aujourd\'hui', 'gerant.residences' => 'Résidences'], 'role' => $u->aRole(Role::Gerant) ? 'Gérant' : 'Personnel'];
        }

        return ['menu' => self::client(), 'bas' => ['compte', 'mes-reservations', 'recherche', 'faq', 'profil'], 'libelles' => ['compte' => 'Accueil', 'mes-reservations' => 'Réservations', 'recherche' => 'Chercher', 'profil' => 'Profil'], 'role' => 'Client'];
    }

    /** Menu latéral du gérant (le personnel n'en voit qu'une partie). */
    public static function gerant(Utilisateur $u): array
    {
        $menu = [
            ['libelle' => 'Aujourd\'hui', 'icone' => 'porte', 'route' => 'gerant.arrivees', 'actif' => ['gerant.arrivees']],
            ['libelle' => 'Calendrier', 'icone' => 'calendrier', 'route' => 'gerant.calendrier', 'actif' => ['gerant.calendrier', 'gerant.telephone']],
        ];
        if ($u->aRole(Role::Gerant)) {
            $menu[] = ['libelle' => 'Réservations', 'icone' => 'liste', 'route' => 'gerant.reservations', 'actif' => ['gerant.reservations', 'gerant.probleme']];
            $menu[] = ['libelle' => 'Argent', 'icone' => 'argent', 'route' => 'gerant.argent', 'actif' => ['gerant.argent']];
            $menu[] = ['libelle' => 'Mes résidences', 'icone' => 'batiment', 'route' => 'gerant.residences', 'actif' => ['gerant.residences', 'gerant.residence.nouvelle', 'gerant.residence']];
            $menu[] = ['libelle' => 'Mon profil gérant', 'icone' => 'compte', 'route' => 'gerant.profil', 'actif' => ['gerant.profil', 'gerant.profil.apercu']];
            $menu[] = ['libelle' => 'Mises en avant', 'icone' => 'etoile', 'route' => 'gerant.mises-en-avant', 'actif' => ['gerant.mises-en-avant', 'gerant.mise-en-avant.acheter', 'gerant.mise-en-avant.retour']];
            $menu[] = ['libelle' => 'Mon personnel', 'icone' => 'equipe', 'route' => 'gerant.personnel', 'actif' => ['gerant.personnel']];
            $menu[] = ['libelle' => 'Aide du gérant', 'icone' => 'aide', 'route' => 'gerant.aide', 'actif' => ['gerant.aide']];
        }

        return $menu;
    }

    /** Menu latéral du client. Chercher et Aide mènent au site public. */
    public static function client(): array
    {
        return [
            ['libelle' => 'Mon espace', 'icone' => 'maison', 'route' => 'compte', 'actif' => ['compte']],
            ['libelle' => 'Mes réservations', 'icone' => 'liste', 'route' => 'mes-reservations', 'actif' => ['mes-reservations', 'reserver', 'payer', 'paiement.retour', 'reservation.confirmation', 'reservation.annuler', 'reservation.detail', 'reservation.avis', 'reservation.probleme']],
            ['libelle' => 'Chercher un logement', 'icone' => 'recherche', 'route' => 'recherche', 'actif' => []],
            ['libelle' => 'Mon profil', 'icone' => 'compte', 'route' => 'profil', 'actif' => ['profil']],
            ['libelle' => 'Aide', 'icone' => 'aide', 'route' => 'faq', 'actif' => []],
        ];
    }

    /** Navigation mobile commune au visiteur et au client, navigateur ou application installée. */
    public static function visiteur(?Utilisateur $u = null): array
    {
        $menu = [
            ['libelle' => 'Accueil', 'icone' => 'maison', 'route' => 'accueil', 'actif' => ['accueil']],
            ['libelle' => 'Logements', 'icone' => 'recherche', 'route' => 'recherche', 'actif' => ['recherche', 'logement', 'hebergeur']],
            ['libelle' => 'Réservations', 'icone' => 'calendrier', 'route' => 'mes-reservations', 'actif' => ['mes-reservations', 'reserver', 'payer', 'paiement.retour', 'reservation.confirmation', 'reservation.annuler', 'reservation.detail', 'reservation.recu', 'reservation.avis']],
            ['libelle' => 'Compte', 'icone' => 'compte', 'route' => 'compte', 'actif' => ['compte', 'profil', 'connexion', 'inscription', 'connexion.lien']],
        ];
        if ($u && ! $u->aRole(Role::Client)) {
            if ($u->estAdmin()) {
                $menu[2]['route'] = 'admin.reservations';
            } elseif ($u->aRole(Role::Gerant)) {
                $menu[2]['route'] = 'gerant.reservations';
            } else {
                $menu[2] = ['libelle' => 'Aide', 'icone' => 'aide', 'route' => 'faq', 'actif' => ['faq', 'contact']];
            }
        }

        return $menu;
    }

    /** Menu de l’en-tête public ; les pages complémentaires restent accessibles depuis le footer. */
    public static function site(): array
    {
        return [
            ['libelle' => 'Accueil', 'route' => 'accueil', 'actif' => ['accueil']],
            ['libelle' => 'Logements', 'route' => 'recherche', 'actif' => ['recherche', 'logement', 'hebergeur']],
            ['libelle' => 'À propos', 'route' => 'a-propos', 'actif' => ['a-propos']],
            ['libelle' => 'Devenir partenaire', 'route' => 'devenir-partenaire', 'actif' => ['devenir-partenaire']],
            ['libelle' => 'Aide', 'route' => 'faq', 'actif' => ['faq']],
            ['libelle' => 'Contact', 'route' => 'contact', 'actif' => ['contact']],
        ];
    }

    /**
     * Menu latéral de l'administration.
     *
     * @return list<array{libelle: string, icone: string, route: string, actif: list<string>}>
     */
    public static function admin(Utilisateur $u): array
    {
        $menu = [
            ['libelle' => 'Tableau de bord', 'icone' => 'maison', 'route' => 'admin.tableau', 'actif' => ['admin.tableau']],
            ['libelle' => 'Résidences à valider', 'icone' => 'batiment', 'route' => 'admin.residences', 'actif' => ['admin.residences', 'admin.residence', 'admin.gerant']],
            ['libelle' => 'Réservations', 'icone' => 'liste', 'route' => 'admin.reservations', 'actif' => ['admin.reservations']],
            ['libelle' => 'Litiges', 'icone' => 'alerte', 'route' => 'admin.litiges', 'actif' => ['admin.litiges']],
            ['libelle' => 'Paiements', 'icone' => 'carte', 'route' => 'admin.paiements', 'actif' => ['admin.paiements']],
            ['libelle' => 'Reversements', 'icone' => 'argent', 'route' => 'admin.reversements', 'actif' => ['admin.reversements']],
            ['libelle' => 'Contrôle', 'icone' => 'balance', 'route' => 'admin.controle', 'actif' => ['admin.controle']],
            ['libelle' => 'Mises en avant', 'icone' => 'etoile', 'route' => 'admin.mises-en-avant', 'actif' => ['admin.mises-en-avant']],
            ['libelle' => 'Journal d\'audit', 'icone' => 'equipe', 'route' => 'admin.journal', 'actif' => ['admin.journal']],
        ];
        if ($u->estSuperAdmin()) {
            $menu[] = ['libelle' => 'Modèles d\'e-mails', 'icone' => 'mail', 'route' => 'admin.messages', 'actif' => ['admin.messages']];
            $menu[] = ['libelle' => 'Réglages', 'icone' => 'reglage', 'route' => 'admin.reglages', 'actif' => ['admin.reglages']];
        }

        return $menu;
    }

    /** Les cinq entrées du menu du bas (téléphone) ; le reste est derrière « Plus ». @return list<string> routes */
    public const ADMIN_BAS = ['admin.tableau', 'admin.reservations', 'admin.paiements', 'admin.controle'];
}
