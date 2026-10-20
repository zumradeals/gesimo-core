<?php

namespace App\Noyau\Enums;

/**
 * Rôles enregistrés en base. Le « visiteur » n'est pas un rôle : c'est une personne non connectée.
 */
enum Role: string
{
    case Client = 'client';
    case Gerant = 'gerant';
    case Personnel = 'personnel';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function libelle(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Gerant => 'Gérant',
            self::Personnel => 'Personnel',
            self::Admin => 'Admin GESIMO',
            self::SuperAdmin => 'Super-administrateur',
        };
    }
}
