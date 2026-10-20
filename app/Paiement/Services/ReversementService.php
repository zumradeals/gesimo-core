<?php

namespace App\Paiement\Services;

use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Paiement\Exceptions\PaiementImpossible;
use App\Paiement\Models\Reversement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Reversement manuel (RG-15) : l'admin envoie l'argent au gérant par Mobile Money, EN DEHORS de la plateforme,
 * puis l'enregistre ici (référence, date). Aucun versement automatique.
 */
class ReversementService
{
    public function enregistrer(Utilisateur $admin, Reversement $reversement, string $reference, CarbonInterface $date): Reversement
    {
        Gate::forUser($admin)->authorize('enregistrer-reversement');

        $reference = trim($reference);
        if ($reference === '') {
            throw new PaiementImpossible('La référence Mobile Money est obligatoire.');
        }
        if ($date->startOfDay()->gt(now()->startOfDay())) {
            throw new PaiementImpossible('La date de l\'envoi ne peut pas être dans le futur.');
        }

        DB::transaction(function () use ($admin, $reversement, $reference, $date) {
            // Un seul enregistrement, même si deux admins cliquent en même temps.
            $pris = Reversement::whereKey($reversement->id)->where('statut', 'a_payer')->update([
                'statut' => 'paye',
                'paye_le' => $date->startOfDay()->addHours(12),
                'paye_par' => $admin->id,
                'reference_mobile_money' => mb_substr($reference, 0, 190),
                'updated_at' => now(),
            ]);
            if ($pris !== 1) {
                throw new PaiementImpossible('Ce reversement a déjà été enregistré.');
            }
            Comptabilite::reversement($reversement->fresh(), $admin);
            Audit::enregistrer('reversement.enregistre', $reversement, ['reference' => $reference, 'montant' => $reversement->montant], $admin);
        });

        return $reversement->fresh();
    }
}
