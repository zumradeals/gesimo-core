<div class="grid gap-4">
    <h1 class="text-2xl font-extrabold">Paiements</h1>
    <div class="grid max-w-md grid-cols-2 overflow-hidden rounded-2xl border border-trait bg-white text-center font-bold">
        <button wire:click="$set('onglet', 'paiements')" class="p-3 {{ $onglet === 'paiements' ? 'bg-marque-600 text-white' : 'text-gris-doux' }}">Paiements</button>
        <button wire:click="$set('onglet', 'ecritures')" class="p-3 {{ $onglet === 'ecritures' ? 'bg-marque-600 text-white' : 'text-gris-doux' }}">Écritures comptables</button>
    </div>

    @if($paiements)
    <div class="carte overflow-x-auto">
        <div class="champ mb-3 max-w-xs"><label class="etiquette" for="statut">État</label><select id="statut" wire:model.live="statut" class="saisie"><option value="">Tous</option><option value="reussi">Réussi</option><option value="en_attente">En attente</option><option value="echoue">Échoué</option></select></div>
        <table class="w-full min-w-[720px] text-[15px]"><thead><tr class="border-b border-trait text-left"><th class="p-2">Date</th><th class="p-2">Référence GeniusPay</th><th class="p-2">Réservation</th><th class="p-2 text-right">Montant</th><th class="p-2 text-right">Frais</th><th class="p-2">État</th><th class="p-2">Mode</th></tr></thead><tbody>
        @forelse($paiements as $p)
            <tr class="border-b border-trait"><td class="p-2">{{ $p->created_at->translatedFormat('j M H:i') }}</td><td class="p-2">{{ $p->reference_geniuspay }}</td><td class="p-2">#{{ $p->reservation_id }} {{ $p->reservation?->client?->nom }}</td><td class="p-2 text-right">{{ number_format($p->montant, 0, ',', ' ') }}</td><td class="p-2 text-right">{{ number_format($p->frais, 0, ',', ' ') }}</td>
                <td class="p-2"><span class="rounded-full px-3 py-0.5 text-[13px] font-bold {{ ['reussi' => 'bg-green-100 text-green-800', 'echoue' => 'bg-red-100 text-red-800', 'en_attente' => 'bg-amber-100 text-amber-800'][$p->statut] }}">{{ ['reussi' => 'Réussi', 'echoue' => 'Échoué', 'en_attente' => 'En attente'][$p->statut] }}</span></td><td class="p-2 aide">{{ $p->mode === 'sandbox' ? 'test' : 'réel' }}</td></tr>
        @empty<tr><td colspan="7" class="p-4 text-center text-gris-doux">Aucun paiement.</td></tr>@endforelse
        </tbody></table>
        <div class="mt-3">{{ $paiements->links() }}</div>
        <p class="aide mt-2">Les paiements ne se modifient jamais : seul leur état avance. Aucun secret n'apparaît ici.</p>
    </div>
    @else
    <div class="carte overflow-x-auto">
        <div class="champ mb-3 max-w-xs"><label class="etiquette" for="type">Type</label><select id="type" wire:model.live="type" class="saisie"><option value="">Tous</option>@foreach(['encaissement', 'commission', 'part_gerant', 'remboursement', 'reversement'] as $t)<option value="{{ $t }}">{{ \App\Paiement\Models\EcritureComptable::libelleType($t) }}</option>@endforeach</select></div>
        <table class="w-full min-w-[640px] text-[15px]"><thead><tr class="border-b border-trait text-left"><th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2">Réservation</th><th class="p-2 text-right">Montant</th><th class="p-2">Par</th></tr></thead><tbody>
        @forelse($ecritures as $e)
            <tr class="border-b border-trait"><td class="p-2">{{ $e->created_at->translatedFormat('j M H:i') }}</td><td class="p-2">{{ \App\Paiement\Models\EcritureComptable::libelleType($e->type) }}</td><td class="p-2">{{ $e->reservation_id ? '#'.$e->reservation_id : '—' }}</td><td class="p-2 text-right">{{ number_format($e->montant, 0, ',', ' ') }}</td><td class="p-2 aide">{{ $e->auteur?->nom ?? 'Système' }}</td></tr>
        @empty<tr><td colspan="5" class="p-4 text-center text-gris-doux">Aucune écriture.</td></tr>@endforelse
        </tbody></table>
        <div class="mt-3">{{ $ecritures->links() }}</div>
        <p class="aide mt-2">Aucune écriture ne se modifie ni ne se supprime. Une correction est une nouvelle écriture.</p>
    </div>
    @endif
</div>
