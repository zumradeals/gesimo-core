<?php

namespace App\Sejours\Services;

/** État d'un document de vérification vu du gérant : à fournir, en contrôle, validé ou refusé (avec le motif). */
class EtatDocument
{
    public const A_FOURNIR = 'a_fournir';

    public const CONTROLE = 'controle';

    public const VALIDE = 'valide';

    public const REFUSE = 'refuse';

    /** @param  object|null  $dernier  Dernier document non remplacé (statut en_attente, accepte ou refuse). */
    public static function de(?object $dernier, bool $valide = false): string
    {
        return match (true) {
            $valide => self::VALIDE,
            $dernier === null => self::A_FOURNIR,
            $dernier->statut === 'accepte' => self::VALIDE,
            $dernier->statut === 'refuse' => self::REFUSE,
            default => self::CONTROLE,
        };
    }

    public static function libelle(string $etat): string
    {
        return ['a_fournir' => 'À fournir', 'controle' => 'En contrôle', 'valide' => 'Validé', 'refuse' => 'Refusé'][$etat] ?? $etat;
    }

    public static function classe(string $etat): string
    {
        return ['a_fournir' => 'att', 'controle' => 'att', 'valide' => 'ok', 'refuse' => 'err'][$etat] ?? 'att';
    }
}
