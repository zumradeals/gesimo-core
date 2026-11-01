<?php

namespace App\Sejours\Services;

use App\Noyau\Enums\Role;
use App\Noyau\Models\Utilisateur;
use App\Noyau\Services\Audit;
use App\Sejours\Enums\StatutReservation;
use App\Sejours\Enums\TypePiece;
use App\Sejours\Models\Checkin;
use App\Sejours\Models\PieceVoyageur;
use App\Sejours\Models\Reservation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pièce d'identité du voyageur : déposée une seule fois dans son profil, chiffrée sur le disque privé,
 * visible seulement du gérant d'une réservation confirmée pendant la durée réglée, chaque ouverture étant journalisée.
 */
class PiecesVoyageur
{
    public const ETAT_A_FOURNIR = 'a_fournir';

    public const ETAT_FOURNIE = 'fournie';

    public const ETAT_VERIFIEE = 'verifiee';

    private const EXTENSIONS = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    /** Statuts pour lesquels le gérant peut consulter la pièce : la réservation est payée et confirmée. */
    public const STATUTS_CONSULTABLES = [StatutReservation::Confirmee, StatutReservation::ArriveeEnregistree, StatutReservation::Terminee, StatutReservation::Litige];

    public static function enregistrer(Utilisateur $voyageur, TypePiece $type, UploadedFile $fichier, ?string $valideJusqu = null): PieceVoyageur
    {
        abort_unless($voyageur->estActif() && $voyageur->aRole(Role::Client), 403);
        $valideJusqu = $valideJusqu === '' ? null : $valideJusqu;

        Validator::make(['piece' => $fichier, 'valide_jusqu' => $valideJusqu], [
            'piece' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'valide_jusqu' => 'nullable|date|after:today',
        ], [
            'piece.required' => 'Choisissez une photo ou un fichier.',
            'piece.file' => 'Ce fichier n\'est pas lisible. Reprenez la photo avec plus de lumière.',
            'piece.mimes' => 'Envoyez une photo (JPG, PNG) ou un PDF.',
            'piece.max' => 'Ce fichier est trop lourd (5 Mo au maximum).',
            'valide_jusqu.date' => 'Indiquez une date valide.',
            'valide_jusqu.after' => 'Cette pièce n\'est plus valable : utilisez une pièce en cours de validité.',
        ])->validate();

        $extension = strtolower($fichier->getClientOriginalExtension() ?: $fichier->guessExtension() ?: '');
        $mime = self::EXTENSIONS[$extension] ?? null;
        $contenu = @file_get_contents($fichier->getRealPath());
        if ($mime === null || $contenu === false || $contenu === '') {
            throw ValidationException::withMessages(['piece' => 'Ce fichier n\'est pas lisible. Reprenez la photo avec plus de lumière.']);
        }

        $chemin = "pieces-voyageurs/{$voyageur->id}/".bin2hex(random_bytes(16)).'.enc';
        $ancien = null;
        try {
            Storage::disk('local')->put($chemin, Crypt::encryptString($contenu));
            if (! Storage::disk('local')->exists($chemin)) {
                throw ValidationException::withMessages(['piece' => 'Le fichier n\'a pas pu être enregistré. Réessayez.']);
            }
            $piece = DB::transaction(function () use ($voyageur, $type, $chemin, $mime, $valideJusqu, &$ancien) {
                $ancien = PieceVoyageur::where('utilisateur_id', $voyageur->id)->lockForUpdate()->first()?->chemin;
                $piece = PieceVoyageur::updateOrCreate(
                    ['utilisateur_id' => $voyageur->id],
                    ['type' => $type->value, 'chemin' => $chemin, 'mime' => $mime, 'valide_jusqu' => $valideJusqu],
                );
                Audit::enregistrer('voyageur.piece_deposee', $piece, ['type' => $type->value], $voyageur);

                return $piece;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($chemin);
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);
            throw ValidationException::withMessages(['piece' => 'L\'enregistrement a échoué. Votre pièce précédente est conservée. Réessayez.']);
        }
        if ($ancien !== null && $ancien !== $chemin) {
            Storage::disk('local')->delete($ancien);
        }

        return $piece;
    }

    /** Suppression par le voyageur, refusée tant qu'une réservation confirmée ou en cours en a besoin. */
    public static function supprimer(Utilisateur $voyageur): void
    {
        $piece = PieceVoyageur::where('utilisateur_id', $voyageur->id)->first();
        if ($piece === null) {
            return;
        }
        $enCours = Reservation::where('client_id', $voyageur->id)
            ->whereIn('statut', [StatutReservation::Confirmee->value, StatutReservation::ArriveeEnregistree->value])->exists();
        if ($enCours) {
            throw ValidationException::withMessages(['piece' => 'Vous avez une réservation en cours : gardez votre pièce jusqu\'à la fin du séjour.']);
        }
        DB::transaction(function () use ($piece, $voyageur) {
            $chemin = $piece->chemin;
            $piece->delete();
            Audit::enregistrer('voyageur.piece_supprimee', null, ['voyageur_id' => $voyageur->id], $voyageur);
            Storage::disk('local')->delete($chemin);
        });
    }

    /** Contenu déchiffré, ou null si le fichier est introuvable. */
    public static function contenu(PieceVoyageur $piece): ?string
    {
        if (! Storage::disk('local')->exists($piece->chemin)) {
            return null;
        }

        return Crypt::decryptString(Storage::disk('local')->get($piece->chemin));
    }

    public static function pieceValide(?Utilisateur $voyageur): ?PieceVoyageur
    {
        if ($voyageur === null) {
            return null;
        }
        $piece = PieceVoyageur::where('utilisateur_id', $voyageur->id)->first();

        return $piece !== null && ! $piece->estExpiree() ? $piece : null;
    }

    /** État de la pièce pour une réservation : à fournir, fournie, vérifiée à l'arrivée (null : sans objet, prise par téléphone). */
    public static function etat(Reservation $reservation): ?string
    {
        if ($reservation->origine !== 'plateforme' || $reservation->client_id === null) {
            return null;
        }
        if (Checkin::where('reservation_id', $reservation->id)->whereNotNull('piece_numero')->exists()) {
            return self::ETAT_VERIFIEE;
        }

        return PieceVoyageur::where('utilisateur_id', $reservation->client_id)->get()
            ->contains(fn (PieceVoyageur $p) => ! $p->estExpiree()) ? self::ETAT_FOURNIE : self::ETAT_A_FOURNIR;
    }

    public static function libelleEtat(?string $etat): string
    {
        return match ($etat) {
            self::ETAT_FOURNIE => 'Fournie',
            self::ETAT_VERIFIEE => 'Vérifiée à l\'arrivée',
            self::ETAT_A_FOURNIR => 'À fournir',
            default => 'Sans objet',
        };
    }

    /** Le gérant peut-il encore ouvrir la pièce ? La réservation doit être confirmée, et le délai après le départ non dépassé. */
    public static function consultablePar(Reservation $reservation): bool
    {
        return $reservation->origine === 'plateforme'
            && in_array($reservation->statut, self::STATUTS_CONSULTABLES, true)
            && now()->startOfDay()->lte($reservation->date_depart->startOfDay()->addDays(ReglesSejour::accesPieceJours()));
    }
}
