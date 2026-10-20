<?php

namespace App\Paiement\Services\Fournisseur;

use RuntimeException;

/** Le fournisseur a refusé la demande (données invalides, clés refusées, remboursement impossible). Ne contient jamais de secret. */
class RequeteRefusee extends RuntimeException {}
