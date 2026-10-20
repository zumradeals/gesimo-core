<?php

namespace App\Sejours\Policies;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\Residence;

/** Droits minimum : chacun ne voit et ne fait que ce que son rôle exige. */
class ResidencePolicy
{
    /** Une résidence vérifiée est publique ; les autres ne sont visibles que par leurs responsables. */
    public function view(?Utilisateur $utilisateur, Residence $residence): bool
    {
        if ($residence->estVerifiee()) {
            return true;
        }

        return $utilisateur !== null && ($utilisateur->estAdmin() || $this->estGerantDe($utilisateur, $residence));
    }

    public function create(Utilisateur $utilisateur): bool
    {
        return $utilisateur->estActif() && $utilisateur->aRole(Role::Gerant);
    }

    public function update(Utilisateur $utilisateur, Residence $residence): bool
    {
        return $utilisateur->estActif() && $this->estGerantDe($utilisateur, $residence);
    }

    /** F-A1 : validation ou refus ; F-A4 : suspension. */
    public function verifier(Utilisateur $utilisateur, Residence $residence): bool
    {
        return $utilisateur->estActif() && $utilisateur->estAdmin();
    }

    public function suspendre(Utilisateur $utilisateur, Residence $residence): bool
    {
        return $this->verifier($utilisateur, $residence);
    }

    private function estGerantDe(Utilisateur $utilisateur, Residence $residence): bool
    {
        return $utilisateur->aRole(Role::Gerant) && $residence->gerant_id === $utilisateur->getKey();
    }
}
