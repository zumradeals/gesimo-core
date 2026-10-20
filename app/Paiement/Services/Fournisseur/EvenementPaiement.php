<?php

namespace App\Paiement\Services\Fournisseur;

/** Message de notification décodé et dont la signature est vérifiée. */
final class EvenementPaiement
{
    public const INITIE = 'paiement.initie';

    public const REUSSI = 'paiement.reussi';

    public const ECHOUE = 'paiement.echoue';

    public const REMBOURSE = 'paiement.rembourse';

    public const AUTRE = 'autre';

    /** @param array<string, mixed> $corps */
    public function __construct(
        public readonly string $type,
        public readonly string $nomFournisseur,
        public readonly ?string $reference,
        public readonly ?int $montant,
        public readonly string $environnement,
        public readonly array $corps,
    ) {}
}
