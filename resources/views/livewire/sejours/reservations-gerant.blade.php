@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $S = \App\Sejours\Enums\StatutReservation::class; @endphp
<div style="display:grid;gap:16px">
    <div class="adm-top"><div><h1>Réservations</h1><p class="petit">Toutes les réservations de vos résidences</p></div><div class="adm-act"><a href="{{ route('gerant.arrivees') }}" class="btn-sec">Arrivées et départs du jour</a></div></div>
    <div class="onglets-a deux" style="max-width:520px">
        <button wire:click="$set('onglet', 'avenir')" class="{{ $onglet === 'avenir' ? 'on' : '' }}" style="border:0;background:{{ $onglet === 'avenir' ? '#fff' : 'transparent' }};padding:10px;border-radius:9px;font-weight:700;cursor:pointer;min-height:44px">À venir ({{ $nbAVenir }})</button>
        <button wire:click="$set('onglet', 'passees')" class="{{ $onglet === 'passees' ? 'on' : '' }}" style="border:0;background:{{ $onglet === 'passees' ? '#fff' : 'transparent' }};padding:10px;border-radius:9px;font-weight:700;cursor:pointer;min-height:44px">Passées ({{ $nbPassees }})</button>
    </div>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($reservations as $r)
            <article class="carte-p">
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
</div>
