<?php

namespace App\Paiement\Services\Fournisseur;

final class EtatPaiement
{
    public const EN_ATTENTE = 'en_attente';

    public const REUSSI = 'reussi';

    public const ECHOUE = 'echoue';

    public const REMBOURSE = 'rembourse';

    public const INCONNU = 'inconnu';

    public function __construct(
        public readonly string $reference,
        public readonly string $statut,
        public readonly ?int $montant,
        public readonly ?string $moyen = null,
    ) {}
}
