@php
    $r = $u->residence;
    $photos = $u->photos->isNotEmpty() ? $u->photos : $r->photos;
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $acompte = null;
    if ($total !== null && $r->mode_paiement === 'acompte' && \App\Paiement\Services\PlancherAcompte::acompteEstPossible()) {
        [$acompte, $solde] = app(\App\Sejours\Services\ReservationService::class)->repartition($u, $total);
    }
@endphp
<div class="grid gap-5">
    <a href="{{ url()->previous(route('accueil')) }}" class="inline-flex items-center gap-1 font-semibold text-marque-600"><x-ui.icone nom="fleche-gauche" classe="h-5 w-5" /> Retour</a>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr] lg:items-start">
        <div class="grid gap-3">
            @if($photos->isNotEmpty())
                <div class="flex snap-x snap-mandatory gap-3 overflow-x-auto rounded-2xl pb-1">
                    @foreach($photos as $p)
                        <img src="{{ $p->url() }}" alt="" class="h-64 w-[88%] shrink-0 snap-center rounded-2xl object-cover lg:h-[420px] lg:w-[70%]" loading="lazy">
                    @endforeach
                </div>
            @else
                <x-ui.photo classe="h-64 lg:h-[420px]" />
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="carte"><h3 class="mb-2 font-extrabold">Équipements</h3>
                    <div class="flex flex-wrap gap-2">@forelse($u->equipements as $e)<span class="rounded-full bg-marque-50 px-3 py-1 text-[14px] text-marque-700">{{ $e->libelle }}</span>@empty<span class="aide">Non précisés</span>@endforelse</div></div>
                <div class="carte"><h3 class="mb-2 font-extrabold">Règlement</h3><p class="aide !text-[15px]">{{ $r->reglement ?: 'Aucun règlement particulier.' }}</p></div>
                <div class="carte"><h3 class="mb-2 font-extrabold">Annulation</h3>
                    @if($r->politique_annulation === 'flexible')
                        <p class="aide !text-[15px]">Remboursement intégral jusqu'à 24 h avant l'arrivée. Moins de 24 h avant : une nuit retenue, le reste remboursé. Absence sans annulation : aucun remboursement.</p>
                    @else
                        <p class="aide !text-[15px]">Politique plus stricte choisie par le gérant. Les conditions précises vous sont affichées avant le paiement.</p>
                    @endif
                </div>
            </div>
            <div class="carte"><h3 class="mb-1 font-extrabold">Avis @if($resumeAvis['nombre'] > 0)<span class="font-normal text-gris-doux">({{ number_format($resumeAvis['moyenne'], 1, ',', '') }} / 5 · {{ $resumeAvis['nombre'] }} séjour{{ $resumeAvis['nombre'] > 1 ? 's' : '' }})</span>@endif</h3>
                @forelse($avis as $a)
                    <div class="border-b border-dashed border-trait py-2 last:border-0"><b>{{ \Illuminate\Support\Str::of($a->client->nom)->before(' ') }}</b> <x-ui.etoiles :note="$a->note" /> <span class="aide">{{ $a->created_at->translatedFormat('M Y') }}</span>@if($a->commentaire)<p class="aide !text-[15px]">« {{ $a->commentaire }} »</p>@endif</div>
                @empty
                    <p class="aide">Pas encore d'avis pour cette résidence.</p>
                @endforelse</div>
            <div class="carte"><h3 class="mb-1 font-extrabold">Où ?</h3>
                <p class="aide !text-[15px]">{{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif. L'adresse exacte, la position GPS et le contact du gérant vous sont donnés après la confirmation de votre réservation.</p></div>
        </div>

        <aside class="carte grid content-start gap-3 lg:sticky lg:top-24">
            <div>
                <h1 class="text-2xl font-extrabold">{{ $u->nom }}</h1>
                <p class="aide">{{ $r->nom }} · {{ $r->commune }} · jusqu'à {{ $u->capacite }} pers.
                    @if($r->badge_verifie) <span class="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-[12px] font-bold text-green-800">✔ Vérifié GESIMO</span> @endif</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="champ"><label class="etiquette" for="arrivee">Arrivée</label><input id="arrivee" type="date" min="{{ now()->toDateString() }}" wire:model.live="arrivee" class="saisie"></div>
                <div class="champ"><label class="etiquette" for="depart">Départ</label><input id="depart" type="date" min="{{ $arrivee ?: now()->toDateString() }}" wire:model.live="depart" class="saisie"></div>
            </div>
            <div class="champ"><label class="etiquette" for="personnes">Personnes</label><input id="personnes" type="number" min="1" max="{{ $u->capacite }}" wire:model.live="personnes" class="saisie"></div>

            @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>
            @elseif($detail === null && $arrivee !== '' && $depart !== '') <x-ui.alerte type="attention">Ce logement n'a pas encore de prix.</x-ui.alerte>
            @elseif($detail !== null)
                <div>
                    <p class="text-3xl font-extrabold">{{ $fr($total) }} <span class="text-sm font-normal text-gris-doux">pour {{ count($detail) }} nuit{{ count($detail) > 1 ? 's' : '' }}</span></p>
                    <div class="mt-2 text-[15px]">
                        @foreach($detail as $nuit)
                            <div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>{{ $nuit['date']->translatedFormat('D j M') }}@if($nuit['type'] === 'week_end') <span class="aide">(week-end)</span>@elseif($nuit['type'] === 'fete') <span class="aide">(période spéciale)</span>@endif</span><span>{{ $fr($nuit['prix']) }}</span></div>
                        @endforeach
                        <div class="flex justify-between py-1.5 font-bold"><span>Total, sans frais cachés</span><span>{{ $fr($total) }}</span></div>
                    </div>
                    @if($acompte !== null)
                        <p class="aide mt-1">Acompte à payer en ligne : {{ $fr($acompte) }}. Le reste ({{ $fr($solde) }}) se règle au gérant à l'arrivée.</p>
                    @else
                        <p class="aide mt-1">Paiement en ligne de la totalité du séjour.</p>
                    @endif
                </div>
                @if($libre)
                    <a href="{{ route('reserver', ['unite' => $u->id, 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes]) }}" class="btn btn-bloc">Réserver</a>
                @else
                    <x-ui.alerte type="erreur">Ces dates ne sont plus libres. Choisissez d'autres dates.</x-ui.alerte>
                @endif
            @else
                <p class="aide">Choisissez vos dates pour voir le prix total du séjour.</p>
            @endif
        </aside>
    </div>
</div>
