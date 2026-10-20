<?php

namespace App\Paiement\Services\Fournisseur;

use App\Parametres\Services\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Passerelle GeniusPay (API Marchand). Tout vient de la documentation officielle fournie par le porteur du projet.
 *
 * Réglages lus dans les Paramètres : geniuspay.mode (sandbox|live), geniuspay.base_url,
 * geniuspay.<mode>.cle_publique, .cle_secrete et .webhook_secret (les secrets sont chiffrés en base).
 * Aucun secret n'apparaît dans les messages d'erreur, les journaux ou les réponses.
 */
class GeniusPay implements FournisseurPaiement
{
    public const URL_PAR_DEFAUT = 'https://geniuspay.ci/api/v1/merchant';

    public function mode(): string
    {
        return Parametres::obtenir('geniuspay.mode', 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public function estConfigure(): bool
    {
        return $this->reglage('cle_publique') !== null && $this->reglage('cle_secrete') !== null && $this->urlDeBase() !== null;
    }

    public function creerPaiement(DemandePaiement $demande): PaiementCree
    {
        $corps = [
            'amount' => $demande->montant,
            'description' => mb_substr($demande->description, 0, 500),
            'external_reference' => $demande->referenceExterne,
            'customer' => array_filter([
                'name' => $demande->nomClient,
                'email' => $demande->emailClient,
                'phone' => $demande->telephoneClient,
            ], fn ($v) => $v !== null && $v !== ''),
            'success_url' => $demande->urlSucces,
            'error_url' => $demande->urlErreur,
            'metadata' => $demande->metadata,
        ];

        // Sans « payment_method » : le client choisit son moyen sur la page sécurisée de GeniusPay.
        $reponse = $this->appeler(fn (PendingRequest $r) => $r->withHeaders(['Idempotency-Key' => $demande->referenceExterne])->post('/payments', $corps));
        $data = $reponse->json('data');

        $reference = is_array($data) ? ($data['reference'] ?? null) : null;
        $url = is_array($data) ? ($data['checkout_url'] ?? null) : null;
        if (! is_string($reference) || $reference === '' || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new FournisseurIndisponible('Réponse inattendue du fournisseur de paiement.');
        }

        return new PaiementCree(
            reference: $reference,
            urlPaiement: $url,
            frais: (int) round((float) ($data['fees'] ?? 0)),
            montantNet: (int) round((float) ($data['net_amount'] ?? $demande->montant)),
            expireLe: isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
            brut: $this->sansSecret($data),
        );
    }

    public function lirePaiement(string $reference): EtatPaiement
    {
        $reference = $this->referenceSure($reference); // vérifiée AVANT l'appel : une référence piégée ne part jamais
        $reponse = $this->appeler(fn (PendingRequest $r) => $r->get('/payments/'.$reference));
        $data = $reponse->json('data');
        if (! is_array($data) || ! isset($data['status'])) {
            throw new FournisseurIndisponible('Réponse inattendue du fournisseur de paiement.');
        }

        return new EtatPaiement(
            reference: (string) ($data['reference'] ?? $reference),
            statut: $this->normaliserStatut((string) $data['status']),
            montant: isset($data['amount']) ? (int) round((float) $data['amount']) : null,
            moyen: $this->moyenConnu($data['payment_method'] ?? null),
        );
    }

    public function rembourser(string $reference, ?int $montant, string $motif): ResultatRemboursement
    {
        $corps = array_filter(['amount' => $montant, 'reason' => mb_substr($motif, 0, 200)], fn ($v) => $v !== null && $v !== '');
        $reference = $this->referenceSure($reference);
        $reponse = $this->appeler(fn (PendingRequest $r) => $r->post('/payments/'.$reference.'/refund', $corps));
        $data = $reponse->json('data');
        if (! is_array($data) || ($data['status'] ?? null) !== 'refunded') {
            throw new FournisseurIndisponible('Réponse inattendue du fournisseur de paiement.');
        }

        return new ResultatRemboursement(
            reference: (string) ($data['refund_reference'] ?? ''),
            montant: (int) round((float) ($data['amount_refunded'] ?? $montant ?? 0)),
        );
    }

    /**
     * Signature : HMAC-SHA256 de « horodatage + "." + corps brut » avec le secret du webhook,
     * en-têtes X-Webhook-Signature / X-Webhook-Timestamp / X-Webhook-Event / X-Webhook-Environment.
     * Sans secret configuré, tout message est refusé (jamais de vérification « sautée »).
     */
    public function lireEvenement(string $corps, array $entetes): EvenementPaiement
    {
        $signature = $entetes['x-webhook-signature'] ?? '';
        $horodatage = $entetes['x-webhook-timestamp'] ?? '';
        $environnement = $entetes['x-webhook-environment'] ?? '';

        if ($signature === '' || $horodatage === '' || ! ctype_digit($horodatage)) {
            throw new SignatureInvalide('En-têtes de signature absents ou invalides.');
        }
        // Un message « test » n'a aucune valeur en mode réel, et inversement.
        if ($environnement !== $this->mode()) {
            throw new SignatureInvalide('Environnement du message différent du mode choisi.');
        }
        $secret = $this->reglage('webhook_secret');
        if ($secret === null) {
            throw new SignatureInvalide('Secret de signature non configuré.');
        }

        $attendue = hash_hmac('sha256', $horodatage.'.'.$corps, $secret);
        if (! hash_equals($attendue, strtolower($signature))) {
            throw new SignatureInvalide('Signature incorrecte.');
        }

        $json = json_decode($corps, true);
        if (! is_array($json)) {
            throw new SignatureInvalide('Corps du message illisible.');
        }
        $transaction = $json['data']['transaction'] ?? [];
        $nom = (string) ($json['event'] ?? ($entetes['x-webhook-event'] ?? ''));

        return new EvenementPaiement(
            type: match ($nom) {
                'payment.initiated' => EvenementPaiement::INITIE,
                'payment.success' => EvenementPaiement::REUSSI,
                'payment.failed', 'payment.cancelled' => EvenementPaiement::ECHOUE,
                'payment.refunded' => EvenementPaiement::REMBOURSE,
                default => EvenementPaiement::AUTRE,
            },
            nomFournisseur: $nom,
            reference: isset($transaction['reference']) ? (string) $transaction['reference'] : null,
            montant: isset($transaction['amount']) ? (int) round((float) $transaction['amount']) : null,
            environnement: $environnement,
            corps: $json,
        );
    }

    // ---------------------------------------------------------------------------------------------

    private function reglage(string $cle): ?string
    {
        $valeur = Parametres::obtenir("geniuspay.{$this->mode()}.{$cle}");

        return $valeur === null || trim($valeur) === '' ? null : trim($valeur);
    }

    /** Toujours en HTTPS : une adresse « http » est refusée. */
    private function urlDeBase(): ?string
    {
        $url = rtrim((string) Parametres::obtenir('geniuspay.base_url', self::URL_PAR_DEFAUT), '/');

        return str_starts_with($url, 'https://') ? $url : null;
    }

    private function appeler(callable $requete): Response
    {
        if (! $this->estConfigure()) {
            throw new FournisseurIndisponible('Le paiement en ligne n\'est pas configuré.');
        }

        $http = Http::baseUrl($this->urlDeBase())
            ->withHeaders(['X-API-Key' => $this->reglage('cle_publique'), 'X-API-Secret' => $this->reglage('cle_secrete')])
            ->acceptJson()->asJson()->timeout(20)->connectTimeout(8);

        try {
            $reponse = $requete($http);
        } catch (ConnectionException) {
            throw new FournisseurIndisponible('Le fournisseur de paiement ne répond pas.');
        } catch (Throwable) {
            // Le détail technique pourrait contenir des en-têtes : on ne le répète jamais.
            throw new FournisseurIndisponible('Erreur de communication avec le fournisseur de paiement.');
        }

        if ($reponse->successful()) {
            return $reponse;
        }

        $code = $reponse->status();
        $code_erreur = (string) ($reponse->json('error.code') ?? $reponse->json('code') ?? '');
        if (in_array($code, [401, 403], true)) {
            throw new RequeteRefusee('Les clés du fournisseur de paiement sont refusées. Vérifiez-les dans les Paramètres.');
        }
        if ($code >= 400 && $code < 500) {
            throw new RequeteRefusee("Demande refusée par le fournisseur de paiement (code {$code}".($code_erreur !== '' ? ", {$code_erreur}" : '').').');
        }

        throw new FournisseurIndisponible("Le fournisseur de paiement a une panne (code {$code}).");
    }

    private function normaliserStatut(string $statut): string
    {
        return match ($statut) {
            'completed' => EtatPaiement::REUSSI,
            'pending', 'processing' => EtatPaiement::EN_ATTENTE,
            'failed', 'cancelled', 'expired' => EtatPaiement::ECHOUE,
            'refunded' => EtatPaiement::REMBOURSE,
            default => EtatPaiement::INCONNU,
        };
    }

    private function moyenConnu(mixed $moyen): ?string
    {
        return match ($moyen) {
            'card' => 'carte',
            'wave', 'orange_money', 'mtn_money', 'moov_money' => 'mobile_money',
            default => null,
        };
    }

    /** La référence est placée dans une adresse : seulement lettres, chiffres, tirets. */
    private function referenceSure(string $reference): string
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{3,80}$/', $reference)) {
            throw new RequeteRefusee('Référence de paiement invalide.');
        }

        return $reference;
    }

    /** @param array<string, mixed> $data */
    private function sansSecret(array $data): array
    {
        unset($data['secret'], $data['api_secret'], $data['api_key']);

        return $data;
    }
}
