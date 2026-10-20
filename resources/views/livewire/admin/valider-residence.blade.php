@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="grid gap-4">
    <a href="{{ route('admin.residences') }}" class="inline-flex items-center gap-1 font-semibold text-marque-600"><x-ui.icone nom="fleche-gauche" classe="h-5 w-5" /> Résidences à vérifier</a>
    <div class="flex flex-wrap items-center justify-between gap-2"><h1 class="text-2xl font-extrabold">{{ $r->nom }}</h1>@include('livewire.sejours.partiels.statut-residence', ['r' => $r])</div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif

    <div class="grid gap-4 lg:grid-cols-[1.3fr_1fr] lg:items-start">
        <div class="grid gap-4">
            <div class="carte grid gap-1">
                <p class="aide">Gérant : {{ $r->gerant->nom }} · {{ \App\Noyau\Services\Telephone::afficher($r->gerant->telephone) }} · {{ $r->gerant->email }}</p>
                <p>{{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif</p>
                <p class="aide !text-[15px]">Paiement : {{ $r->mode_paiement === 'acompte' ? 'acompte de '.$r->pourcentage_acompte.' %' : 'tout en ligne' }} · annulation {{ $r->politique_annulation }}</p>
                @if($r->reglement)<p class="aide !text-[15px]">Règlement : {{ $r->reglement }}</p>@endif
            </div>
            <div class="carte"><h2 class="mb-2 font-extrabold">Photos</h2>
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">@forelse($r->photos as $p)<img src="{{ $p->urlMiniature() }}" alt="Photo de la résidence" width="480" height="320" class="h-28 w-full rounded-lg object-cover" loading="lazy" decoding="async">@empty<p class="aide">Aucune photo.</p>@endforelse</div></div>
            <div class="carte"><h2 class="mb-2 font-extrabold">Logements</h2>
                @foreach($r->unites as $u)<div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>{{ $u->nom }} <span class="aide">({{ $u->type }}, {{ $u->capacite }} pers.)</span></span><span>{{ ($p = $u->tarifs->firstWhere('type_periode', 'standard')) ? $fr($p->prix_nuit) : 'sans prix' }}</span></div>@endforeach</div>
            <div class="carte"><h2 class="mb-2 font-extrabold">Pièces</h2>
                @forelse($r->documents as $d)<div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>{{ $d->libelle() }}</span><a href="{{ route('admin.document', $d) }}" target="_blank" class="font-semibold text-marque-600 underline">Voir le document</a></div>@empty<p class="aide">Aucune pièce envoyée.</p>@endforelse</div>
        </div>

        <div class="carte grid gap-3 lg:sticky lg:top-24">
            <h2 class="text-lg font-extrabold">Décision</h2>
            <label class="puce {{ $badge ? 'puce-active' : '' }}"><input type="checkbox" class="sr-only" wire:model.live="badge">Accorder le badge « Vérifié GESIMO »</label>
            <button wire:click="valider" wire:confirm="Valider et mettre cette résidence en ligne ?" class="btn btn-bloc" wire:loading.attr="disabled">Valider et mettre en ligne</button>
            <div class="champ"><label class="etiquette" for="motif">Motif du refus</label><textarea id="motif" rows="2" wire:model="motifRefus" class="saisie py-2" placeholder="Ex. Photo floue, justificatif manquant"></textarea>@error('motifRefus')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button wire:click="refuser" class="btn btn-danger btn-bloc">Refuser</button>
            <p class="aide">Chaque décision est écrite dans le journal d'audit.</p>
        </div>
    </div>
</div>
