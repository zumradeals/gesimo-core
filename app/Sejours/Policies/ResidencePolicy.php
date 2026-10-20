<?php

namespace App\Sejours\Policies;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Sejours\Models\MembreResidence;
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

    /** Calendrier : le gérant, ou un membre du personnel à qui le gérant a donné ce droit. */
    public function gererCalendrier(Utilisateur $utilisateur, Residence $residence): bool
    {
        return $utilisateur->estActif() && ($this->estGerantDe($utilisateur, $residence) || $this->membreAvecDroit($utilisateur, $residence, 'peut_gerer_calendrier'));
    }

    public function gererPersonnel(Utilisateur $utilisateur, Residence $residence): bool
    {
        return $utilisateur->estActif() && $this->estGerantDe($utilisateur, $residence);
    }

    private function membreAvecDroit(Utilisateur $utilisateur, Residence $residence, string $droit): bool
    {
        return $utilisateur->aRole(Role::Personnel)
            && MembreResidence::where('residence_id', $residence->getKey())
                ->where('utilisateur_id', $utilisateur->getKey())
                ->where($droit, true)
                ->exists();
    }

    private function estGerantDe(Utilisateur $utilisateur, Residence $residence): bool
    {
        return $utilisateur->aRole(Role::Gerant) && $residence->gerant_id === $utilisateur->getKey();
    }
}
