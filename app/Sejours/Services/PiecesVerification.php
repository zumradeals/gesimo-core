<?php

namespace App\Sejours\Services;

use App\Noyau\Services\Audit;
use App\Sejours\Models\DocumentVerification;
use App\Sejours\Models\Residence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Justificatifs de gestion facultatifs. Les pièces personnelles appartiennent au dossier du gérant. */
class PiecesVerification
{
    public const CHAMPS = ['justificatif_droit_gestion' => 'justificatif'];

    /** @param array<string, UploadedFile> $fichiers Pièces nouvelles seulement, indexées par leur type. */
    public static function enregistrer(Residence $residence, array $fichiers): bool
    {
        Gate::authorize('update', $residence);
        if ($fichiers === []) {
            return false;
        }

        $chemins = [];
        try {
            foreach ($fichiers as $type => $fichier) {
                abort_unless(array_key_exists($type, self::CHAMPS), 422);
                $champ = self::CHAMPS[$type];
                $chemin = $fichier->store("documents/{$residence->id}", 'local');
                if ($chemin === false) {
                    throw ValidationException::withMessages([$champ => 'Le fichier n’a pas pu être enregistré. Réessayez.']);
                }
                $chemins[$type] = $chemin;
                if (! Storage::disk('local')->exists($chemin)) {
                    throw ValidationException::withMessages([$champ => 'Le fichier n’a pas pu être enregistré. Réessayez.']);
                }
            }

            DB::transaction(function () use ($residence, $chemins) {
                $residence = Residence::lockForUpdate()->findOrFail($residence->id);
                foreach ($chemins as $type => $chemin) {
                    $residence->documents()->where('type', $type)->where('statut', '<>', 'accepte')->whereNull('remplace_le')->update(['remplace_le' => now()]);
                    DocumentVerification::create(['residence_id' => $residence->id, 'type' => $type, 'chemin' => $chemin, 'statut' => 'en_attente']);
                }
                Audit::enregistrer('residence.pieces_enregistrees', $residence, ['types' => array_keys($chemins)]);
            });
        } catch (Throwable $e) {
            foreach ($chemins as $chemin) {
                Storage::disk('local')->delete($chemin);
            }
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);
            throw ValidationException::withMessages(['pieces' => 'Les pièces n’ont pas pu être enregistrées. Vos anciennes pièces sont conservées. Réessayez.']);
        }

        return true;
    }
}
