@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $t = $bilan['totaux']; @endphp
<div class="grid gap-4">
    <div class="adm-top"><div><h1>Contrôle</h1></div></div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif

    @if($bilan['anomalies']->isEmpty())
        <div class="carte-p"><b>✔ Tout concorde</b> <span class="aide">— paiements et écritures sont d'accord, au franc près.</span></div>
    @else
        <div class="carte-p"><b class="text-red-800">⚠ {{ $bilan['anomalies']->count() }} différence{{ $bilan['anomalies']->count() > 1 ? 's' : '' }} à examiner</b></div>
    @endif

    <div class="kpi">
        <div class="k"><b>{{ $fr($t['encaisse']) }}</b><span>Encaissé (paiements réussis)</span></div>
        <div class="k"><b>{{ $fr($t['rembourse']) }}</b><span>Remboursé</span></div>
        <div class="k"><b>{{ $fr($t['commissions']) }}</b><span>Commissions</span></div>
        <div class="k"><b>{{ $fr($t['parts']) }}</b><span>Parts des gérants</span></div></div>
    <div class="carte-p">
        <h2 class="font-extrabold">Calcul</h2>
        <div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>Encaissé</span><span>{{ $fr($t['encaisse']) }}</span></div>
        <div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>− Remboursé − Commissions − Parts des gérants</span><span>− {{ $fr($t['rembourse'] + $t['commissions'] + $t['parts']) }}</span></div>
        <div class="flex justify-between py-1.5"><b>= Fonds encore détenus par GESIMO (séjours pas encore arrivés)</b><b>{{ $fr($bilan['detenus']) }}</b></div>
        <p class="aide">Déjà reversé aux gérants (hors plateforme, par Mobile Money) : {{ $fr($t['reverse']) }}.</p>
    </div>

    @if($bilan['anomalies']->isNotEmpty())
    <div class="grid gap-2">
        <h2>Différences</h2>
        @foreach($bilan['anomalies'] as $a)
            <x-ui.alerte type="erreur">{{ $a['message'] }}
                @if($a['action'] === 'ecrire_encaissement')<button wire:click="ecrireEncaissement({{ $a['id'] }})" class="ml-2 rounded-lg border border-red-700 bg-white px-3 py-1 font-bold">Corriger par une nouvelle écriture</button>@endif
                @if($a['action'] === 'ecrire_remboursement')<button wire:click="ecrireRemboursement({{ $a['id'] }})" class="ml-2 rounded-lg border border-red-700 bg-white px-3 py-1 font-bold">Corriger par une nouvelle écriture</button>@endif
            </x-ui.alerte>
        @endforeach
    </div>
    @endif

    <h2>À traiter ({{ $alertes->count() }})</h2>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($alertes as $al)
            <article class="carte-p">
                <div><span class="rounded-full bg-amber-100 px-3 py-0.5 text-[13px] font-bold text-amber-800">{{ \App\Paiement\Models\AlerteAdmin::libelleType($al->type) }}</span></div>
                <p class="text-[15px]">{{ $al->message }}</p>
                @if($al->type === 'remboursement_en_echec' && $al->remboursement_id && $enEchec->has($al->remboursement_id))
                    <button wire:click="relancer({{ $al->remboursement_id }})" wire:loading.attr="disabled" class="btn-pl pleine">Réessayer le remboursement</button>
                    <div class="champ"><input wire:model="references.{{ $al->remboursement_id }}" class="saisie" placeholder="Référence si fait à la main"></div>
                    <button wire:click="faitMain({{ $al->remboursement_id }})" class="btn-sec pleine">Je l'ai fait à la main : enregistrer</button>
                @else
                    <button wire:click="traiter({{ $al->id }})" class="btn-sec pleine">Marquer comme traité</button>
                @endif
            </article>
        @empty
            <p class="aide">Rien à traiter.</p>
        @endforelse
    </div>
    @if($refusesRecents > 0)<x-ui.alerte type="attention">{{ $refusesRecents }} message{{ $refusesRecents > 1 ? 's' : '' }} de « GeniusPay » refusé{{ $refusesRecents > 1 ? 's' : '' }} ces 7 derniers jours (signature incorrecte). Ils ont été ignorés : aucune réservation n'a été confirmée.</x-ui.alerte>@endif
</div>
