<div class="grid gap-4">
    <h1 class="text-2xl font-extrabold">Toutes les réservations</h1>
    <div class="champ max-w-xs"><label class="etiquette" for="statut">Statut</label>
        <select id="statut" wire:model.live="statut" class="saisie"><option value="">Tous</option>@foreach($statuts as $s)<option value="{{ $s->value }}">{{ $s->libelle() }}</option>@endforeach</select></div>
    <div class="carte overflow-x-auto p-0 lg:p-0">
        <table class="w-full min-w-[640px] text-left text-[15px]">
            <thead><tr class="border-b border-trait text-gris-doux"><th class="p-3">N°</th><th class="p-3">Client</th><th class="p-3">Logement</th><th class="p-3">Dates</th><th class="p-3">Statut</th><th class="p-3 text-right">Total</th></tr></thead>
            <tbody>
            @forelse($reservations as $r)
                <tr class="border-b border-trait last:border-0"><td class="p-3">#{{ $r->id }}</td><td class="p-3">{{ $r->client?->nom ?? $r->nom_client }}</td><td class="p-3">{{ $r->unite->nom }} <span class="aide">· {{ $r->unite->residence->nom }}</span></td><td class="p-3 whitespace-nowrap">{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}</td><td class="p-3"><x-ui.badge-statut :statut="$r->statut" /></td><td class="p-3 text-right whitespace-nowrap">{{ \App\Noyau\Services\Format::fcfa($r->montant_total) }}</td></tr>
            @empty
                <tr><td colspan="6" class="p-4 text-gris-doux">Aucune réservation.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $reservations->links() }}
</div>
