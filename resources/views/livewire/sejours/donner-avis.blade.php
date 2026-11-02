@php $libelles = [1 => 'Décevant', 2 => 'Moyen', 3 => 'Bien', 4 => 'Très bien', 5 => 'Excellent']; @endphp
<div class="mr-page ev-page">
    <p class="fil"><a href="{{ route('reservation.detail', $r) }}">← Ma réservation</a></p>
    <div class="ev-centre">
    @if($fait)
        <div class="mr-carte ev-succes"><div class="ev-gros-ico">⭐</div><h1>Merci ! Votre avis est publié.</h1><p class="petit" style="margin:0">Un avis ne peut plus être modifié.</p><a href="{{ route('mes-reservations') }}" class="btn-sec">Mes réservations</a></div>
    @else
    <form wire:submit="publier" class="mr-carte">
        <h1>Comment s'est passé votre séjour ?</h1>
        <p class="petit" style="margin:0">{{ $r->unite->nom }} · {{ $r->unite->residence->nom }} · {{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}</p>
        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
        <div role="radiogroup" aria-label="Note">
            <div class="ev-etoiles">
                @foreach(range(1, 5) as $n)
                    <button type="button" wire:click="choisir({{ $n }})" role="radio" aria-checked="{{ $n <= $note ? 'true' : 'false' }}" aria-label="{{ $n }} étoile(s)" class="{{ $n <= $note ? 'on' : '' }}">★</button>
                @endforeach
            </div>
            <p class="ev-note-lib">{{ $note ? $note.' / 5 · '.$libelles[$note] : 'Touchez une étoile pour noter' }}</p>
        </div>
        <div class="champ"><label class="etiquette" for="commentaire">Votre commentaire (facultatif)</label><textarea id="commentaire" wire:model="commentaire" rows="4" class="saisie py-2" maxlength="1000" placeholder="Ce que vous avez aimé, ce qui pourrait être mieux…"></textarea></div>
        <p class="petit" style="margin:0">Votre avis sera publié sur la fiche de la résidence avec votre prénom. Une fois publié, il ne peut plus être modifié.</p>
        <button class="btn-pl grand pleine" wire:loading.attr="disabled">Publier mon avis</button>
    </form>
    @endif
    </div>
</div>
