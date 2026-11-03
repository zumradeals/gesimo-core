<div class="mr-page ev-page">
    <div class="mr-entete"><div><h1>Litiges</h1></div></div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
    <div class="gq-cartes">
        @forelse($ouverts as $l)
            <article class="mr-carte">
                <div class="flex items-center justify-between gap-2"><b>Litige n° {{ $l->id }}</b><span class="rounded-full bg-amber-100 px-3 py-0.5 text-[13px] font-bold text-amber-800">Ouvert</span></div>
                <p class="aide">Réservation n° {{ $l->reservation_id }} · {{ $l->reservation->unite->nom }} ({{ $l->reservation->unite->residence->nom }}) · ouvert par {{ $l->auteur->nom }} · contre {{ $l->partieAdverse?->nom ?? '—' }}</p>
                <p class="rounded-xl bg-fond p-3">« {{ $l->motif }} »</p>
                <div class="champ"><label class="etiquette" for="d{{ $l->id }}">Votre décision</label><textarea id="d{{ $l->id }}" wire:model="decisions.{{ $l->id }}" rows="3" class="saisie py-2" placeholder="Expliquez la décision aux deux parties"></textarea></div>
                <button wire:click="clore({{ $l->id }})" wire:confirm="Clore ce litige ? Les deux parties recevront votre décision." class="btn-pl pleine">Clore le litige</button>
                <p class="aide">La réservation retrouve son statut d'avant. Un éventuel remboursement ne se fait pas ici : il passe par le circuit de remboursement existant (Admin ▸ Contrôle).</p>
            </article>
        @empty
            <div class="mr-carte"><p class="font-semibold" style="margin:0">Aucun litige ouvert.</p></div>
        @endforelse
    </div>
    @if($clos->isNotEmpty())
        <h2>Litiges clos</h2>
        <div class="gq-cartes">@foreach($clos as $l)<div class="mr-carte"><b>Litige n° {{ $l->id }}</b> <span class="rounded-full bg-green-100 px-3 py-0.5 text-[13px] font-bold text-green-800">Résolu</span><p class="aide">Réservation n° {{ $l->reservation_id }} · décidé le {{ $l->decide_le?->translatedFormat('j M Y') }}</p><p class="text-[15px]">Décision : {{ $l->decision }}</p></div>@endforeach</div>
    @endif
</div>
