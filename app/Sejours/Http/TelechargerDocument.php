<?php

namespace App\Sejours\Http;

use App\Noyau\Services\Audit;
use App\Sejours\Models\DocumentVerification;
use App\Sejours\Models\Residence;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Les pièces d'identité et justificatifs ne sont jamais publics : lecture réservée à l'équipe Avelis, et journalisée. */
class TelechargerDocument
{
    public function __invoke(DocumentVerification $document)
    {
        Gate::authorize('verifier', Residence::findOrFail($document->residence_id));
        abort_unless(Storage::disk('local')->exists($document->chemin), 404);

        Audit::enregistrer('document.consulte', $document, ['residence_id' => $document->residence_id]);

        return Storage::disk('local')->download($document->chemin, basename($document->chemin));
    }
}
