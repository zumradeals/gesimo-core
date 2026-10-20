<?php

namespace App\Paiement\Services\Fournisseur;

final class ResultatRemboursement
{
    public function __construct(public readonly string $reference, public readonly int $montant) {}
}
