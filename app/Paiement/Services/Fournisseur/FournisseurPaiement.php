<?php

namespace App\Paiement\Services\Fournisseur;

/**
 * Le fournisseur de paiement est derrière cette interface : GeniusPay aujourd'hui,
 * un autre demain, sans toucher au reste de GESIMO.
 */
interface FournisseurPaiement
{
    /** Vrai seulement si les clés du mode choisi (test ou réel) sont saisies. */
    public function estConfigure(): bool;

    /** « sandbox » (test) ou « live » (réel), lu dans les Paramètres. */
    public function mode(): string;

    /** @throws FournisseurIndisponible|RequeteRefusee */
    public function creerPaiement(DemandePaiement $demande): PaiementCree;

    /**
     * Test de connexion (RG-21) : une demande en lecture seule, sans argent en jeu, qui prouve que les clés sont acceptées.
     *
     * @throws FournisseurIndisponible|RequeteRefusee
     */
    public function tester(): void;

    /** @throws FournisseurIndisponible|RequeteRefusee */
    public function lirePaiement(string $reference): EtatPaiement;

    /** @throws FournisseurIndisponible|RequeteRefusee */
    public function rembourser(string $reference, ?int $montant, string $motif): ResultatRemboursement;

    /**
     * Vérifie la signature d'un message reçu du fournisseur et le décode.
     *
     * @param  array<string, string>  $entetes  en-têtes HTTP en minuscules
     *
     * @throws SignatureInvalide
     */
    public function lireEvenement(string $corps, array $entetes): EvenementPaiement;
}
