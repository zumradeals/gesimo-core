<div class="grid gap-4" wire:poll.30s>
    <h1 class="text-2xl font-extrabold">Mes réservations</h1>
    <div class="grid max-w-md grid-cols-2 overflow-hidden rounded-xl border border-trait bg-white text-center font-bold">
        <button wire:click="$set('onglet', 'avenir')" class="min-h-[48px] {{ $onglet === 'avenir' ? 'bg-marque-600 text-white' : '' }}">À venir ({{ $nbAVenir }})</button>
        <button wire:click="$set('onglet', 'passees')" class="min-h-[48px] {{ $onglet === 'passees' ? 'bg-marque-600 text-white' : '' }}">Passées ({{ $nbPassees }})</button>
    </div>
    @if(session('message')) <x-ui.alerte type="succes">{{ session('message') }}</x-ui.alerte> @endif
    @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif

    @forelse($reservations as $r)
        @php $u = $r->unite; $res = $u->residence; @endphp
        <article class="carte grid gap-1.5 md:max-w-3xl">
            <div class="flex items-center justify-between gap-2">
                <x-ui.badge-statut :statut="$r->statut" />
                <span class="aide">#{{ $r->id }}</span>
            </div>
            <h2 class="text-lg font-extrabold"><a href="{{ route('reservation.detail', $r) }}" class="underline decoration-trait underline-offset-4">{{ $u->nom }} · {{ $res->nom }}</a></h2>
            <p class="aide">{{ $r->date_arrivee->translatedFormat('j M Y') }} → {{ $r->date_depart->translatedFormat('j M Y') }} · {{ $r->nombre_nuits ?? $r->date_arrivee->diffInDays($r->date_depart) }} nuits</p>
            <p class="text-2xl font-extrabold">{{ \App\Noyau\Services\Format::fcfa($r->montant_total) }}</p>

            @if($r->statut === \App\Sejours\Enums\StatutReservation::EnAttente)
                <x-ui.alerte type="attention"><x-ui.icone nom="horloge" classe="mr-1 inline h-5 w-5" /> Dates gardées encore <b>{{ $r->expire_le->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</b>.</x-ui.alerte>
                @if(\App\Paiement\Models\Paiement::where('reservation_id', $r->id)->where('statut', 'en_attente')->exists())
                    <a href="{{ route('paiement.retour', $r) }}" class="font-semibold text-marque-600 underline">Un paiement est en cours de vérification</a>
                @endif
                <a href="{{ route('payer', $r) }}" data-reseau class="btn btn-bloc">Payer {{ \App\Noyau\Services\Format::fcfa($r->acompte) }}</a>
                <button data-reseau wire:click="annuler({{ $r->id }})" wire:confirm="Annuler cette réservation ?" class="btn btn-danger btn-bloc">Annuler cette réservation</button>
            @elseif(in_array($r->statut, [\App\Sejours\Enums\StatutReservation::Confirmee, \App\Sejours\Enums\StatutReservation::ArriveeEnregistree], true))
                <div class="rounded-xl bg-marque-50 p-3 text-[15px]">
                    <p class="font-bold">Contact du gérant</p>
                    <p>{{ $res->gerant->nom }} · {{ \App\Noyau\Services\Telephone::afficher($res->gerant->telephone) }}</p>
                    @if($res->latitude !== null)<a class="font-semibold text-marque-600 underline" href="https://www.google.com/maps?q={{ $res->latitude }},{{ $res->longitude }}" target="_blank" rel="noopener">Voir sur la carte</a>@endif
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <a href="{{ route('reservation.recu', $r) }}" target="_blank" class="btn btn-second btn-bloc">Reçu</a>
                    @if($r->statut === \App\Sejours\Enums\StatutReservation::Confirmee)<a href="{{ route('reservation.annuler', $r) }}" data-reseau class="btn btn-danger btn-bloc">Annuler</a>@endif
                </div>
            @elseif($r->statut === \App\Sejours\Enums\StatutReservation::Expiree)
                <p class="aide">Le délai de garde est passé : les dates ont été libérées.</p>
            @endif
        </article>
    @empty
        <div class="carte grid place-items-center gap-3 py-10 text-center md:max-w-xl">
            <x-ui.icone nom="liste" classe="h-10 w-10 text-marque-500" />
            <p class="text-lg font-semibold">{{ $onglet === 'passees' ? 'Aucun séjour passé.' : 'Aucune réservation à venir pour le moment.' }}</p>
            <a href="{{ route('accueil') }}" class="btn">Chercher un séjour</a>
        </div>
    @endforelse
</div>
