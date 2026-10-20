<?php

namespace App\Paiement\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapprochement : compare les paiements enregistrés et les écritures comptables, et signale toute différence.
 *
 * Fonds détenus par GESIMO = encaissements − remboursements − commissions − parts du gérant.
 * Pour chaque réservation : tant que rien n'est « acquis » (réservation confirmée, pas encore arrivée), commission et part
 * doivent être nulles ; une fois acquis, commission + part + remboursement (effectué ou demandé) = somme encaissée.
 */
class Rapprochement
{
    /**
     * @return array{totaux: array<string, int>, detenus: int, anomalies: Collection<int, array{type: string, message: string, action: ?string, id: ?int}>}
     */
    public function calculer(): array
    {
        $anomalies = collect();

        $paiements = DB::table('paiement')->where('statut', 'reussi')->whereNotNull('reservation_id')->get(['id', 'reference_geniuspay', 'montant', 'reservation_id']);
        $encaissements = DB::table('ecriture_comptable')->where('type', 'encaissement')->whereNull('correction_de')->get()->keyBy('paiement_id');

        foreach ($paiements as $p) {
            $e = $encaissements->get($p->id);
            if ($e === null) {
                $anomalies->push(['type' => 'encaissement_manquant', 'message' => "Paiement {$p->reference_geniuspay} ({$p->montant} FCFA) réussi sans écriture d'encaissement.", 'action' => 'ecrire_encaissement', 'id' => $p->id]);
            } elseif ((int) $e->montant !== (int) $p->montant) {
                $anomalies->push(['type' => 'encaissement_different', 'message' => "Paiement {$p->reference_geniuspay} : {$p->montant} FCFA encaissés mais écriture de {$e->montant} FCFA.", 'action' => null, 'id' => $p->id]);
            }
        }

        $remboursements = DB::table('remboursement')->where('statut', 'effectue')->get(['id', 'montant', 'reservation_id']);
        $ecrituresRemboursement = DB::table('ecriture_comptable')->where('type', 'remboursement')->whereNull('correction_de')->get()->keyBy('remboursement_id');
        foreach ($remboursements as $r) {
            $e = $ecrituresRemboursement->get($r->id);
            if ($e === null) {
                $anomalies->push(['type' => 'remboursement_sans_ecriture', 'message' => "Remboursement #{$r->id} ({$r->montant} FCFA) effectué sans écriture.", 'action' => 'ecrire_remboursement', 'id' => $r->id]);
            } elseif ((int) $e->montant !== (int) $r->montant) {
                $anomalies->push(['type' => 'remboursement_different', 'message' => "Remboursement #{$r->id} : {$r->montant} FCFA remboursés mais écriture de {$e->montant} FCFA.", 'action' => null, 'id' => $r->id]);
            }
        }

        // Reversements payés : chacun doit avoir son écriture.
        $ecrituresReversement = DB::table('ecriture_comptable')->where('type', 'reversement')->whereNull('correction_de')->get()->keyBy('reversement_id');
        foreach (DB::table('reversement')->where('statut', 'paye')->get(['id', 'montant']) as $rv) {
            if (! $ecrituresReversement->has($rv->id)) {
                $anomalies->push(['type' => 'reversement_sans_ecriture', 'message' => "Reversement #{$rv->id} payé sans écriture.", 'action' => null, 'id' => $rv->id]);
            }
        }

        // Réservation par réservation.
        $lignes = DB::table('reservation')
            ->join('paiement', fn ($j) => $j->on('paiement.reservation_id', '=', 'reservation.id')->where('paiement.statut', 'reussi'))
            ->groupBy('reservation.id', 'reservation.statut')
            ->get(['reservation.id', 'reservation.statut', DB::raw('SUM(paiement.montant) AS paye')]);
        $parResa = DB::table('ecriture_comptable')->whereNull('correction_de')->whereIn('type', ['commission', 'part_gerant'])
            ->selectRaw('reservation_id, type, SUM(montant) AS total')->groupBy('reservation_id', 'type')->get()->groupBy('reservation_id');
        $rembResa = DB::table('remboursement')->whereIn('statut', ['effectue', 'en_attente'])->selectRaw('reservation_id, SUM(montant) AS total')->groupBy('reservation_id')->pluck('total', 'reservation_id');

        foreach ($lignes as $l) {
            $ecr = $parResa->get($l->id, collect())->pluck('total', 'type');
            $acquis = (int) ($ecr['commission'] ?? 0) + (int) ($ecr['part_gerant'] ?? 0);
            $rembourse = (int) ($rembResa[$l->id] ?? 0);
            $paye = (int) $l->paye;

            if (in_array($l->statut, ['confirmee', 'en_attente', 'expiree'], true) && $acquis !== 0) {
                $anomalies->push(['type' => 'acquis_trop_tot', 'message' => "Réservation #{$l->id} ({$l->statut}) : commission/part déjà écrites ({$acquis} FCFA) alors que rien n'est acquis.", 'action' => null, 'id' => $l->id]);
            } elseif (in_array($l->statut, ['arrivee_enregistree', 'terminee', 'annulee'], true) && $acquis + $rembourse !== $paye) {
                $anomalies->push(['type' => 'partage_incomplet', 'message' => "Réservation #{$l->id} : payé {$paye} FCFA, mais commission + part + remboursement = ".($acquis + $rembourse).' FCFA.', 'action' => null, 'id' => $l->id]);
            }
        }

        $encaisse = (int) DB::table('paiement')->where('statut', 'reussi')->sum('montant');
        $ecrEnc = (int) DB::table('ecriture_comptable')->where('type', 'encaissement')->sum('montant');
        $remb = (int) DB::table('ecriture_comptable')->where('type', 'remboursement')->sum('montant');
        $comm = (int) DB::table('ecriture_comptable')->where('type', 'commission')->sum('montant');
        $parts = (int) DB::table('ecriture_comptable')->where('type', 'part_gerant')->sum('montant');
        $detenus = $ecrEnc - $remb - $comm - $parts;

        if ($encaisse !== $ecrEnc) {
            $anomalies->push(['type' => 'total_different', 'message' => "Total encaissé : {$encaisse} FCFA dans les paiements, {$ecrEnc} FCFA dans les écritures.", 'action' => null, 'id' => null]);
        }
        if ($detenus < 0) {
            $anomalies->push(['type' => 'fonds_negatifs', 'message' => "Fonds détenus négatifs ({$detenus} FCFA) : plus d'argent distribué que reçu.", 'action' => null, 'id' => null]);
        }

        return [
            'totaux' => ['encaisse' => $encaisse, 'rembourse' => $remb, 'commissions' => $comm, 'parts' => $parts, 'reverse' => (int) DB::table('ecriture_comptable')->where('type', 'reversement')->sum('montant')],
            'detenus' => $detenus,
            'anomalies' => $anomalies,
        ];
    }
}
