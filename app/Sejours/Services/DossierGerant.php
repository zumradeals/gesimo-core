<?php

namespace App\Sejours\Services;

use App\Noyau\Enums\Role;
use App\Noyau\Services\Audit;
use App\Sejours\Models\ProfilGerant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class DossierGerant
{
    public static function autoriserProprietaire(ProfilGerant $profil): void
    {
        abort_unless(auth()->user()?->estActif() && auth()->user()->aRole(Role::Gerant) && $profil->utilisateur_id === auth()->id(), 403);
    }

    /** @param array<string, UploadedFile> $fichiers */
    public static function enregistrer(ProfilGerant $profil, array $fichiers): void
    {
        self::autoriserProprietaire($profil);
        $chemins = [];
        foreach ($fichiers as $type => $fichier) {
            abort_unless(in_array($type, ['identite', 'certificat_residence'], true), 422);
            Validator::make([$type => $fichier], [$type => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'], [
                "$type.mimes" => 'Choisissez un document PDF, JPEG ou PNG.',
                "$type.max" => 'Le document ne doit pas dépasser 5 Mo.',
            ])->validate();
        }
        try {
            foreach ($fichiers as $type => $fichier) {
                $chemin = $fichier->store("dossiers-gerants/{$profil->id}", 'local');
                if ($chemin === false) {
                    throw ValidationException::withMessages([$type => 'Le document n’a pas pu être enregistré. Réessayez.']);
                }
                $chemins[$type] = $chemin;
                if (! Storage::disk('local')->exists($chemin)) {
                    throw ValidationException::withMessages([$type => 'Le document n’a pas pu être enregistré. Réessayez.']);
                }
            }
            DB::transaction(function () use ($profil, $chemins) {
                ProfilGerant::lockForUpdate()->findOrFail($profil->id);
                foreach ($chemins as $type => $chemin) {
                    // L'ancienne preuve acceptée reste valable jusqu'à l'acceptation de sa remplaçante.
                    $profil->documents()->where('type', $type)->where('statut', '<>', 'accepte')->whereNull('remplace_le')->update(['remplace_le' => now()]);
                    $document = $profil->documents()->create(['type' => $type, 'chemin' => $chemin]);
                    Audit::enregistrer('gerant.document_depose', $document, ['type' => $type]);
                }
            });
        } catch (Throwable $e) {
            foreach ($chemins as $chemin) {
                Storage::disk('local')->delete($chemin);
            }
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);
            throw ValidationException::withMessages(['documents' => 'L’enregistrement a échoué. Vos documents précédents restent conservés. Réessayez.']);
        }
    }

    public static function decider(int $profilId, int $documentId, bool $accepter, string $motif = ''): void
    {
        Gate::authorize('voir-verifications');
        if (! $accepter && trim($motif) === '') {
            throw ValidationException::withMessages(['motif' => 'Indiquez le motif du refus.']);
        }
        DB::transaction(function () use ($profilId, $documentId, $accepter, $motif) {
            $profil = ProfilGerant::lockForUpdate()->findOrFail($profilId);
            $document = $profil->documents()->whereNull('remplace_le')->findOrFail($documentId);
            if ($accepter && $document->statut === 'accepte') {
                return;
            }
            if ($accepter && ! Storage::disk('local')->exists($document->chemin)) {
                throw ValidationException::withMessages(['documents' => 'Ce fichier est indisponible. Demandez un nouveau document au gérant.']);
            }
            if ($accepter) {
                $profil->documents()->where('type', $document->type)->where('id', '<>', $document->id)->whereNull('remplace_le')->update(['remplace_le' => now()]);
            }
            $document->update(['statut' => $accepter ? 'accepte' : 'refuse', 'motif_refus' => $accepter ? null : trim($motif), 'verifie_par' => auth()->id(), 'verifie_le' => now()]);
            if ($accepter && $document->type === 'identite') {
                $profil->update(['reprise_a_revoir' => false]);
            }
            Audit::enregistrer($accepter ? 'gerant.document_accepte' : 'gerant.document_refuse', $document, ['type' => $document->type]);
        });
    }
}
