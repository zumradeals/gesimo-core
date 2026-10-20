@php
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $u = $r->unite; $res = $u->residence;
    $S = \App\Sejours\Enums\StatutReservation::class;
    $contactVisible = in_array($r->statut, [$S::Confirmee, $S::ArriveeEnregistree, $S::Terminee, $S::Litige], true);
@endphp
<div class="grid gap-4">
    <a href="{{ route('mes-reservations') }}" class="text-[15px] font-semibold text-marque-600">← Mes réservations</a>
    <div class="grid gap-4 md:grid-cols-2 md:items-start">
        <div class="carte grid gap-1">
            <div class="flex items-center justify-between gap-2"><x-ui.badge-statut :statut="$r->statut" /><span class="aide">Réservation n° {{ $r->id }}</span></div>
            <h1 class="text-xl font-extrabold">{{ $u->nom }}</h1>
            <p class="aide">{{ $res->nom }} · {{ $res->commune }}</p>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Dates</span><span>{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M Y') }}</span></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Total du séjour</span><b>{{ $fr($r->montant_total) }}</b></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Payé en ligne</span><b>{{ $fr($paye) }}</b></div>
            @if($r->solde > 0 && $contactVisible)<div class="flex justify-between py-2"><span>À régler au gérant à l'arrivée</span><b>{{ $fr($r->solde) }}</b></div>@endif
            @if($litigeOuvert)<x-ui.alerte type="attention" class="mt-2">Vous avez signalé un problème : l'équipe GESIMO l'étudie et vous répond sous 48 h.</x-ui.alerte>@endif
        </div>
        <div class="carte grid gap-2">
            @if($contactVisible)
                <h2 class="text-lg font-extrabold">Le gérant</h2>
                <p><b>{{ $res->gerant->nom }}</b><br>{{ \App\Noyau\Services\Telephone::afficher($res->gerant->telephone) }}</p>
                @if($res->latitude !== null)<a class="font-semibold text-marque-600 underline" href="https://www.google.com/maps?q={{ $res->latitude }},{{ $res->longitude }}" target="_blank" rel="noopener">Voir sur la carte</a>@endif
            @endif
            @if($r->statut === $S::EnAttente)
                <a href="{{ route('payer', $r) }}" data-reseau class="btn btn-bloc">Payer {{ $fr($r->acompte) }}</a>
            @endif
            @if($paye > 0)<a href="{{ route('reservation.recu', $r) }}" data-reseau target="_blank" class="btn btn-second btn-bloc">Télécharger le reçu</a>@endif
            @if($r->statut === $S::Confirmee)<a href="{{ route('reservation.annuler', $r) }}" data-reseau class="btn btn-danger btn-bloc">Annuler</a>@endif
            @if($r->statut === $S::Terminee)
                @if($avis)
                    <x-ui.alerte type="succes">Merci pour votre avis : <x-ui.etoiles :note="$avis->note" /></x-ui.alerte>
                @else
                    <a href="{{ route('reservation.avis', $r) }}" data-reseau class="btn btn-bloc">Donner mon avis</a>
                @endif
            @elseif(in_array($r->statut, [$S::Confirmee, $S::ArriveeEnregistree], true))
                <x-ui.alerte type="info">Après le séjour, vous pourrez donner votre avis ici.</x-ui.alerte>
            @endif
            @if(! $litigeOuvert && in_array($r->statut, [$S::Confirmee, $S::ArriveeEnregistree, $S::Terminee, $S::Annulee, $S::Litige], true) && $r->origine !== 'hors_plateforme')
                <a href="{{ route('reservation.probleme', $r) }}" data-reseau class="btn btn-second btn-bloc border-trait text-gris-doux">Signaler un problème</a>
            @endif
        </div>
    </div>
</div>
