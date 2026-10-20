<?php

namespace App\Paiement\Services\Fournisseur;

use RuntimeException;

/** Le fournisseur ne répond pas, n'est pas configuré ou a une panne : on peut réessayer plus tard. */
class FournisseurIndisponible extends RuntimeException {}
