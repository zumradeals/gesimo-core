<?php

namespace App\Paiement\Services\Fournisseur;

final class DemandePaiement
{
    /** @param array<string, scalar> $metadata */
    public function __construct(
        public readonly int $montant,
        public readonly string $description,
        public readonly string $referenceExterne,
        public readonly string $nomClient,
        public readonly ?string $emailClient,
        public readonly ?string $telephoneClient,
        public readonly string $urlSucces,
        public readonly string $urlErreur,
        public readonly array $metadata = [],
    ) {}
}
