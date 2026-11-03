<div class="mr-page ev-page">
    <div class="mr-entete"><div><h1>Journal d'audit</h1><p class="petit">Qui a fait quoi, et quand. Le journal ne se modifie jamais.</p></div></div>
    <div class="flex flex-wrap gap-2">@foreach($filtres as $cle => $nom)<button wire:click="$set('filtre', '{{ $cle }}')" class="puce {{ $filtre === $cle ? 'puce-active' : '' }}">{{ $nom }}</button>@endforeach</div>
    <div class="carte overflow-x-auto p-0 lg:p-0">
        <table class="tab-r w-full min-w-[560px] text-left text-[15px]">
            <thead><tr class="border-b border-trait text-gris-doux"><th class="p-3">Quand</th><th class="p-3">Qui</th><th class="p-3">Action</th><th class="p-3">Sur</th></tr></thead>
            <tbody>
            @forelse($lignes as $l)
                <tr class="border-b border-trait last:border-0"><td data-l="Quand" class="p-3 whitespace-nowrap">{{ $l->created_at->translatedFormat('j M H:i') }}</td><td data-l="Qui" class="p-3">{{ $noms[$l->utilisateur_id] ?? ($l->utilisateur_id ? '#'.$l->utilisateur_id : 'Système') }}</td><td data-l="Action" class="p-3">{{ $l->action }}</td><td data-l="Sur" class="p-3">{{ $l->objet_type }} {{ $l->objet_id ? '#'.$l->objet_id : '' }}</td></tr>
            @empty
                <tr><td colspan="4" class="p-4 text-gris-doux">Rien à afficher.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="aide">Lecture seule : le journal ne se modifie jamais.</p>
    {{ $lignes->links() }}
</div>
