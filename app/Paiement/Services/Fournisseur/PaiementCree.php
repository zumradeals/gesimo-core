<?php

namespace App\Paiement\Services\Fournisseur;

use Carbon\CarbonImmutable;

final class PaiementCree
{
    /** @param array<string, mixed> $brut */
    public function __construct(
        public readonly string $reference,
        public readonly string $urlPaiement,
        public readonly int $frais,
        public readonly int $montantNet,
        public readonly ?CarbonImmutable $expireLe,
        public readonly array $brut,
    ) {}
}
