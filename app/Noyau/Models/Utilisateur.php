<?php

namespace App\Noyau\Models;

use App\Noyau\Enums\Role;
use App\Sejours\Models\ProfilGerant;
use App\Sejours\Models\Reservation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Utilisateur extends Authenticatable
{
    use Notifiable;

    protected $table = 'utilisateur';

    protected $fillable = ['nom', 'telephone', 'email', 'mot_de_passe', 'statut'];

    protected $attributes = ['statut' => 'actif'];

    protected $hidden = ['mot_de_passe', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verifie_le' => 'datetime',
            'mot_de_passe' => 'hashed',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe';
    }

    public function roles(): HasMany
    {
        return $this->hasMany(RoleUtilisateur::class);
    }

    public function profilGerant(): HasOne
    {
        return $this->hasOne(ProfilGerant::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }

    public function estActif(): bool
    {
        return $this->statut === 'actif';
    }

    /**
     * Le super-administrateur fait « tout ce que fait l'admin » (cahier, section 2) ;
     * aucun autre rôle n'en hérite un autre.
     */
    public function aRole(Role $role): bool
    {
        $roles = $this->roles->pluck('role');

        if ($roles->contains(Role::SuperAdmin) && $role === Role::Admin) {
            return true;
        }

        return $roles->contains($role);
    }

    public function estAdmin(): bool
    {
        return $this->aRole(Role::Admin);
    }

    public function estSuperAdmin(): bool
    {
        return $this->aRole(Role::SuperAdmin);
    }

    public function attribuerRole(Role $role): RoleUtilisateur
    {
        $attribution = $this->roles()->firstOrCreate(['role' => $role->value]);
        $this->unsetRelation('roles');

        return $attribution;
    }
}
