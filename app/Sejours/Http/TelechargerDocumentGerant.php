<?php

namespace App\Sejours\Http;

use App\Noyau\Services\Audit;
use App\Sejours\Models\DocumentGerant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TelechargerDocumentGerant
{
    public function __invoke(DocumentGerant $document)
    {
        Gate::authorize('voir-verifications');
        abort_unless(Storage::disk('local')->exists($document->chemin), 404);
        Audit::enregistrer('gerant.document_consulte', $document);

        return Storage::disk('local')->download($document->chemin, basename($document->chemin));
    }
}
