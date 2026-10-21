<div class="grid gap-4">
    <div class="adm-top"><div><h1>Journal d'audit</h1></div></div>
    <div class="flex flex-wrap gap-2">@foreach($filtres as $cle => $nom)<button wire:click="$set('filtre', '{{ $cle }}')" class="puce {{ $filtre === $cle ? 'puce-active' : '' }}">{{ $nom }}</button>@endforeach</div>
    <div class="carte overflow-x-auto p-0 lg:p-0">
        <table class="w-full min-w-[560px] text-left text-[15px]">
            <thead><tr class="border-b border-trait text-gris-doux"><th class="p-3">Quand</th><th class="p-3">Qui</th><th class="p-3">Action</th><th class="p-3">Sur</th></tr></thead>
            <tbody>
            @forelse($lignes as $l)
                <tr class="border-b border-trait last:border-0"><td class="p-3 whitespace-nowrap">{{ $l->created_at->translatedFormat('j M H:i') }}</td><td class="p-3">{{ $noms[$l->utilisateur_id] ?? ($l->utilisateur_id ? '#'.$l->utilisateur_id : 'Système') }}</td><td class="p-3">{{ $l->action }}</td><td class="p-3">{{ $l->objet_type }} {{ $l->objet_id ? '#'.$l->objet_id : '' }}</td></tr>
            @empty
                <tr><td colspan="4" class="p-4 text-gris-doux">Rien à afficher.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="aide">Lecture seule : le journal ne se modifie jamais.</p>
    {{ $lignes->links() }}
</div>
