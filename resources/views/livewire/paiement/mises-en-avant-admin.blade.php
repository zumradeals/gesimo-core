@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="grid gap-4">
    <h1 class="text-2xl font-extrabold">Mises en avant</h1>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
    <div class="carte w-fit"><b class="text-xl">{{ $fr($revenus) }}</b><p class="aide">Revenus des mises en avant (pour GESIMO)</p></div>
    <div class="flex flex-wrap gap-2">
        @foreach(['en_cours' => 'En cours ('.$compteurs['en_cours'].')', 'programmees' => 'Programmées ('.$compteurs['programmees'].')', 'terminees' => 'Terminées', 'suspendues' => 'Suspendues ('.$compteurs['suspendues'].')'] as $k => $n)
            <button wire:click="$set('filtre', '{{ $k }}')" class="puce {{ $filtre === $k ? 'puce-active' : '' }}">{{ $n }}</button>
        @endforeach
    </div>
    <div class="carte overflow-x-auto p-0 lg:p-0">
        <table class="w-full min-w-[680px] text-left text-[15px]">
            <thead><tr class="border-b border-trait text-gris-doux"><th class="p-3">Résidence</th><th class="p-3">Gérant</th><th class="p-3">Période</th><th class="p-3 text-right">Prix</th><th class="p-3">Statut</th><th class="p-3"></th></tr></thead>
            <tbody>
            @forelse($mises as $m)
                <tr class="border-b border-trait last:border-0"><td class="p-3">{{ $m->residence->nom }}</td><td class="p-3">{{ $m->residence->gerant->nom }}</td>
                    <td class="whitespace-nowrap p-3">{{ $m->date_debut->translatedFormat('j M') }} → {{ $m->date_fin->translatedFormat('j M Y') }}</td><td class="whitespace-nowrap p-3 text-right">{{ $fr($m->prix) }}</td>
                    <td class="p-3">{{ $m->etat() }}</td>
                    <td class="p-3">@if($m->statut === 'active')<button wire:click="demanderSuspension({{ $m->id }})" class="min-h-[44px] font-bold text-red-700">Suspendre</button>@elseif($m->statut === 'suspendue')<button wire:click="reprendre({{ $m->id }})" class="min-h-[44px] font-bold text-marque-600">Reprendre</button>@endif</td></tr>
            @empty
                <tr><td colspan="6" class="p-4 text-gris-doux">Aucune mise en avant ici.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($suspension)
        <form wire:submit="suspendre" class="carte grid gap-2 md:max-w-xl">
            <h2 class="text-lg font-extrabold">Suspendre la mise en avant n° {{ $suspension }}</h2>
            <div class="champ"><label class="etiquette" for="motif">Motif (gardé dans le journal)</label><textarea id="motif" wire:model="motif" rows="2" class="saisie py-2"></textarea></div>
            <button class="btn btn-danger btn-bloc">Suspendre la mise en avant</button>
            <p class="aide">L'annonce redevient normale tout de suite. <b>Aucun remboursement automatique</b> : la période n'est pas prolongée ; « Reprendre » la remet en avant jusqu'à sa date de fin.</p>
        </form>
    @endif
</div>
