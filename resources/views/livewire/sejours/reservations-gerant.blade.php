@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $S = \App\Sejours\Enums\StatutReservation::class; @endphp
<div class="grid gap-4">
    <h1 class="text-2xl font-extrabold">Réservations</h1>
    <div class="grid max-w-md grid-cols-2 overflow-hidden rounded-xl border border-trait bg-white text-center font-bold">
        <button wire:click="$set('onglet', 'avenir')" class="min-h-[48px] {{ $onglet === 'avenir' ? 'bg-marque-600 text-white' : '' }}">À venir ({{ $nbAVenir }})</button>
        <button wire:click="$set('onglet', 'passees')" class="min-h-[48px] {{ $onglet === 'passees' ? 'bg-marque-600 text-white' : '' }}">Passées ({{ $nbPassees }})</button>
    </div>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($reservations as $r)
            <article class="carte grid gap-1.5">
                <div class="flex items-center justify-between gap-2"><x-ui.badge-statut :statut="$r->statut" /><span class="aide">#{{ $r->id }}</span></div>
                <h2 class="text-lg font-extrabold">{{ $r->client?->nom ?? $r->nom_client }} · {{ $r->unite->nom }}</h2>
                <p class="aide">{{ $r->unite->residence->nom }} · {{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}</p>
                <button wire:click="detail({{ $r->id }})" class="text-left text-[15px] font-semibold text-marque-600">{{ $ouverte === $r->id ? 'Masquer le détail' : 'Voir le détail' }}</button>
                @if($ouverte === $r->id)
                    <div class="grid gap-1 rounded-xl bg-marque-50 p-3 text-[15px]">
                        <div class="flex justify-between"><span>Contact</span><span>{{ \App\Noyau\Services\Telephone::afficher($r->client?->telephone ?? $r->telephone_client ?? '') }}</span></div>
                        <div class="flex justify-between"><span>Total du séjour</span><b>{{ $fr($r->montant_total) }}</b></div>
                        @if($r->origine !== 'hors_plateforme')
                            <div class="flex justify-between"><span>Payé en ligne</span><b>{{ $fr((int) $r->paiements()->where('statut', 'reussi')->sum('montant')) }}</b></div>
                            @if($r->solde > 0)<div class="flex justify-between"><span>À encaisser à l'arrivée</span><b>{{ $fr($r->solde) }}</b></div>@endif
                        @endif
                    </div>
                    @if($r->origine !== 'hors_plateforme' && in_array($r->statut, [$S::Confirmee, $S::ArriveeEnregistree, $S::Terminee, $S::Annulee, $S::Litige], true) && ! in_array($r->id, $litigesOuverts, true))
                        <a href="{{ route('gerant.probleme', $r) }}" class="btn btn-second btn-bloc border-trait text-gris-doux">Signaler un problème</a>
                    @endif
                @endif
            </article>
        @empty
            <div class="carte md:col-span-2"><p class="font-semibold">{{ $onglet === 'passees' ? 'Aucune réservation passée.' : 'Aucune réservation à venir pour le moment.' }}</p><p class="aide">Les réservations confirmées apparaissent ici.</p></div>
        @endforelse
    </div>
    <a href="{{ route('gerant.arrivees') }}" class="btn btn-second md:w-auto">Arrivées et départs du jour</a>
</div>
