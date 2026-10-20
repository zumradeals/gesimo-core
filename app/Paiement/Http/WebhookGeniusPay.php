<?php

namespace App\Paiement\Http;

use App\Paiement\Models\EvenementPaiement as EvenementEnregistre;
use App\Paiement\Services\Fournisseur\FournisseurIndisponible;
use App\Paiement\Services\Fournisseur\FournisseurPaiement;
use App\Paiement\Services\Fournisseur\SignatureInvalide;
use App\Paiement\Services\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Réception des messages de GeniusPay.
 *  - signature invalide ou absente : 401, aucun effet, trace conservée ;
 *  - fournisseur momentanément injoignable : 503 (GeniusPay renverra le message plus tard) ;
 *  - tout le reste : 200, y compris un message déjà traité (idempotence).
 */
class WebhookGeniusPay
{
    public function __invoke(Request $request, FournisseurPaiement $fournisseur, PaiementService $paiements): JsonResponse
    {
        $corps = $request->getContent();
        if (strlen($corps) > 65536) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        $entetes = [];
        foreach ($request->headers->all() as $nom => $valeurs) {
            $entetes[strtolower($nom)] = (string) ($valeurs[0] ?? '');
        }

        try {
            $evenement = $fournisseur->lireEvenement($corps, $entetes);
        } catch (SignatureInvalide $e) {
            // On garde la trace du refus (sans le corps ni la signature : un message falsifié n'a aucune valeur).
            EvenementEnregistre::create([
                'reference' => null,
                'evenement' => mb_substr($entetes['x-webhook-event'] ?? '', 0, 60) ?: null,
                'environnement' => mb_substr($entetes['x-webhook-environment'] ?? '', 0, 10) ?: null,
                'signature_valide' => false,
                'corps' => null,
                'resultat' => mb_substr('refuse : '.$e->getMessage(), 0, 80),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        try {
            $resultat = $paiements->traiterEvenement($evenement);
        } catch (FournisseurIndisponible) {
            return response()->json(['error' => 'Retry later'], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['success' => true, 'result' => $resultat]);
    }
}
