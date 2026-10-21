<?php

namespace App\Noyau\Messagerie;

use App\Noyau\Models\Utilisateur;
use App\Parametres\Services\Parametres;

/**
 * Textes des messages envoyés par la plateforme. Les valeurs par défaut sont ici ; l'administrateur
 * peut les remplacer dans « Modèles d'e-mails » (réglages message.<code>.sujet et message.<code>.corps).
 * Les mots entre accolades sont remplacés au moment de l'envoi.
 */
class ModelesMessages
{
    public const MOTS = [
        'nom' => 'le prénom ou nom du destinataire',
        'logement' => 'le nom du logement',
        'residence' => 'le nom de la résidence',
        'dates' => 'les dates du séjour',
        'montant' => 'le montant concerné',
        'numero' => 'le numéro de la réservation',
        'motif' => 'le motif (litige)',
        'decision' => 'la décision (litige)',
    ];

    /** @return array<string, array{titre: string, sujet: string, corps: string, destinataire: string}> */
    public static function defauts(): array
    {
        return [
            'confirmation' => [
                'titre' => 'Confirmation de réservation',
                'destinataire' => 'client',
                'sujet' => 'Réservation confirmée : {logement}',
                'corps' => "Bonjour {nom},\n\nVotre réservation n° {numero} est confirmée.\nLogement : {logement} ({residence})\nDates : {dates}\nMontant payé : {montant}\n\nVous retrouvez le reçu et les coordonnées du gérant dans « Mes réservations ».\n\nBon séjour !",
            ],
            'rappel_arrivee' => [
                'titre' => 'Rappel d\'arrivée (la veille)',
                'destinataire' => 'client',
                'sujet' => 'Votre arrivée demain : {logement}',
                'corps' => "Bonjour {nom},\n\nNous vous attendons demain, {dates}, à {logement} ({residence}).\n\nBon voyage !",
            ],
            'annulation' => [
                'titre' => 'Annulation',
                'destinataire' => 'client et gérant',
                'sujet' => 'Réservation annulée : {logement}',
                'corps' => "Bonjour {nom},\n\nLa réservation n° {numero} ({logement}, {dates}) est annulée.\nMontant remboursé : {montant}\n\nSi un remboursement est prévu, il peut prendre un peu de temps selon votre opérateur.",
            ],
            'reversement' => [
                'titre' => 'Reversement effectué (au gérant)',
                'destinataire' => 'gérant',
                'sujet' => 'Reversement effectué : {montant}',
                'corps' => "Bonjour {nom},\n\nAvelis vient de vous reverser {montant} pour la réservation n° {numero} ({logement}).\n\nVous retrouvez le détail dans « Argent ».",
            ],
            'litige_ouvert' => [
                'titre' => 'Litige ouvert',
                'destinataire' => 'les deux parties',
                'sujet' => 'Un problème a été signalé : réservation n° {numero}',
                'corps' => "Bonjour {nom},\n\nUn problème a été signalé pour la réservation n° {numero} ({logement}, {dates}).\nMotif : {motif}\n\nL'équipe Avelis étudie la situation et vous répond sous 48 h.",
            ],
            'litige_clos' => [
                'titre' => 'Litige clos',
                'destinataire' => 'les deux parties',
                'sujet' => 'Problème traité : réservation n° {numero}',
                'corps' => "Bonjour {nom},\n\nLe problème signalé pour la réservation n° {numero} ({logement}) est clos.\nDécision de l'équipe Avelis : {decision}",
            ],
        ];
    }

    /** @return array{sujet: string, corps: string} */
    public static function obtenir(string $code): array
    {
        $defaut = self::defauts()[$code] ?? throw new \InvalidArgumentException("Modèle de message inconnu : {$code}");

        return [
            'sujet' => Parametres::obtenir("message.{$code}.sujet") ?: $defaut['sujet'],
            'corps' => Parametres::obtenir("message.{$code}.corps") ?: $defaut['corps'],
        ];
    }

    public static function definir(string $code, string $sujet, string $corps, ?Utilisateur $auteur = null): void
    {
        Parametres::definir("message.{$code}.sujet", trim($sujet), $auteur, false, 'messages');
        Parametres::definir("message.{$code}.corps", trim($corps), $auteur, false, 'messages');
    }

    /** @return list<string> Les mots entre accolades qui n'existent pas. */
    public static function motsInconnus(string $texte): array
    {
        preg_match_all('/\{(\w+)\}/u', $texte, $m);

        return array_values(array_unique(array_diff($m[1], array_keys(self::MOTS))));
    }

    /** @param array<string, scalar|null> $valeurs */
    public static function remplir(string $texte, array $valeurs): string
    {
        return preg_replace_callback('/\{(\w+)\}/u', fn ($m) => (string) ($valeurs[$m[1]] ?? $m[0]), $texte);
    }
}
