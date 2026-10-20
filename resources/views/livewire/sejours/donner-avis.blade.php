<div class="mx-auto grid max-w-xl gap-4">
    <a href="{{ route('reservation.detail', $r) }}" class="text-[15px] font-semibold text-marque-600">← Ma réservation</a>
    @if($fait)
        <div class="carte grid justify-items-center gap-2 py-8 text-center"><div class="text-4xl">⭐</div><h1 class="text-xl font-extrabold">Merci ! Votre avis est publié.</h1><p class="aide">Un avis ne peut plus être modifié.</p><a href="{{ route('mes-reservations') }}" class="btn btn-second">Mes réservations</a></div>
    @else
    <form wire:submit="publier" class="carte grid gap-3">
        <h1 class="text-xl font-extrabold">Comment s'est passé votre séjour ?</h1>
        <p class="aide">{{ $r->unite->nom }} · {{ $r->unite->residence->nom }} · {{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}</p>
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
        <div class="flex gap-1" role="radiogroup" aria-label="Note">
            @foreach(range(1, 5) as $n)
                <button type="button" wire:click="choisir({{ $n }})" role="radio" aria-checked="{{ $n <= $note ? 'true' : 'false' }}" aria-label="{{ $n }} étoile(s)" class="min-h-[56px] min-w-[56px] text-4xl {{ $n <= $note ? 'text-amber-500' : 'text-trait' }}">★</button>
            @endforeach
        </div>
        <div class="champ"><label class="etiquette" for="commentaire">Votre commentaire (facultatif)</label><textarea id="commentaire" wire:model="commentaire" rows="4" class="saisie py-2" maxlength="1000"></textarea></div>
        <button class="btn btn-bloc" wire:loading.attr="disabled">Publier mon avis</button>
    </form>
    @endif
</div>
