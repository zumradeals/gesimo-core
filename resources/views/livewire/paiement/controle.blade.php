@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $t = $bilan['totaux']; @endphp
<div class="grid gap-4">
    <h1 class="text-2xl font-extrabold">Contrôle</h1>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif

    @if($bilan['anomalies']->isEmpty())
        <div class="carte border-green-300 bg-green-50"><b>✔ Tout concorde</b> <span class="aide">— paiements et écritures sont d'accord, au franc près.</span></div>
    @else
        <div class="carte border-red-300 bg-red-50"><b class="text-red-800">⚠ {{ $bilan['anomalies']->count() }} différence{{ $bilan['anomalies']->count() > 1 ? 's' : '' }} à examiner</b></div>
    @endif

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="carte"><p class="text-2xl font-extrabold">{{ $fr($t['encaisse']) }}</p><p class="aide">Encaissé (paiements réussis)</p></div>
        <div class="carte"><p class="text-2xl font-extrabold">{{ $fr($t['rembourse']) }}</p><p class="aide">Remboursé</p></div>
        <div class="carte"><p class="text-2xl font-extrabold">{{ $fr($t['commissions']) }}</p><p class="aide">Commissions</p></div>
        <div class="carte"><p class="text-2xl font-extrabold">{{ $fr($t['parts']) }}</p><p class="aide">Parts des gérants</p></div>
    </div>
    <div class="carte grid gap-1">
        <h2 class="font-extrabold">Calcul</h2>
        <div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>Encaissé</span><span>{{ $fr($t['encaisse']) }}</span></div>
        <div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>− Remboursé − Commissions − Parts des gérants</span><span>− {{ $fr($t['rembourse'] + $t['commissions'] + $t['parts']) }}</span></div>
        <div class="flex justify-between py-1.5"><b>= Fonds encore détenus par GESIMO (séjours pas encore arrivés)</b><b>{{ $fr($bilan['detenus']) }}</b></div>
        <p class="aide">Déjà reversé aux gérants (hors plateforme, par Mobile Money) : {{ $fr($t['reverse']) }}.</p>
    </div>

    @if($bilan['anomalies']->isNotEmpty())
    <div class="grid gap-2">
        <h2 class="text-lg font-extrabold">Différences</h2>
        @foreach($bilan['anomalies'] as $a)
            <x-ui.alerte type="erreur">{{ $a['message'] }}
                @if($a['action'] === 'ecrire_encaissement')<button wire:click="ecrireEncaissement({{ $a['id'] }})" class="ml-2 rounded-lg border border-red-700 bg-white px-3 py-1 font-bold">Corriger par une nouvelle écriture</button>@endif
                @if($a['action'] === 'ecrire_remboursement')<button wire:click="ecrireRemboursement({{ $a['id'] }})" class="ml-2 rounded-lg border border-red-700 bg-white px-3 py-1 font-bold">Corriger par une nouvelle écriture</button>@endif
            </x-ui.alerte>
        @endforeach
    </div>
    @endif

    <h2 class="text-lg font-extrabold">À traiter ({{ $alertes->count() }})</h2>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($alertes as $al)
            <article class="carte grid gap-2">
                <div><span class="rounded-full bg-amber-100 px-3 py-0.5 text-[13px] font-bold text-amber-800">{{ \App\Paiement\Models\AlerteAdmin::libelleType($al->type) }}</span></div>
                <p class="text-[15px]">{{ $al->message }}</p>
                @if($al->type === 'remboursement_en_echec' && $al->remboursement_id && $enEchec->has($al->remboursement_id))
                    <button wire:click="relancer({{ $al->remboursement_id }})" wire:loading.attr="disabled" class="btn btn-bloc">Réessayer le remboursement</button>
                    <div class="champ"><input wire:model="references.{{ $al->remboursement_id }}" class="saisie" placeholder="Référence si fait à la main"></div>
                    <button wire:click="faitMain({{ $al->remboursement_id }})" class="btn btn-second btn-bloc">Je l'ai fait à la main : enregistrer</button>
                @else
                    <button wire:click="traiter({{ $al->id }})" class="btn btn-second btn-bloc">Marquer comme traité</button>
                @endif
            </article>
        @empty
            <p class="aide">Rien à traiter.</p>
        @endforelse
    </div>
    @if($refusesRecents > 0)<x-ui.alerte type="attention">{{ $refusesRecents }} message{{ $refusesRecents > 1 ? 's' : '' }} de « GeniusPay » refusé{{ $refusesRecents > 1 ? 's' : '' }} ces 7 derniers jours (signature incorrecte). Ils ont été ignorés : aucune réservation n'a été confirmée.</x-ui.alerte>@endif
</div>
