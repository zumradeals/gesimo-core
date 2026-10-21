@php
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $u = $r->unite; $res = $u->residence;
    $S = \App\Sejours\Enums\StatutReservation::class;
    $contactVisible = in_array($r->statut, [$S::Confirmee, $S::ArriveeEnregistree, $S::Terminee, $S::Litige], true);
    $etapes = [
        ['Réservation créée · '.$r->created_at->translatedFormat('j M'), true],
        ['Acompte payé', $paye > 0],
        ['Confirmée', in_array($r->statut, [$S::Confirmee, $S::ArriveeEnregistree, $S::Terminee, $S::Litige], true)],
        ['Arrivée · '.$r->date_arrivee->translatedFormat('j M'), in_array($r->statut, [$S::ArriveeEnregistree, $S::Terminee], true)],
        ['Séjour terminé', $r->statut === $S::Terminee],
    ];
@endphp
<div style="display:grid;gap:14px">
    <p class="fil"><a href="{{ route('mes-reservations') }}">Mes réservations</a> › Réservation n° {{ $r->id }}</p>
    <div class="cols">
        <div class="col-g">
            <section class="carte-p">
                <div class="ri-t"><h1 style="font-size:22px">{{ $u->nom }} · {{ $res->nom }}</h1><x-ui.badge-statut :statut="$r->statut" /></div>
                <p class="petit" style="margin:0">{{ $res->commune }}@if($res->quartier), {{ $res->quartier }}@endif</p>
                <div class="duo2">
                    <div><span class="petit">Arrivée</span><b>{{ $r->date_arrivee->translatedFormat('D j M Y') }}</b></div>
                    <div><span class="petit">Départ</span><b>{{ $r->date_depart->translatedFormat('D j M Y') }}</b></div>
                    <div><span class="petit">Personnes</span><b>{{ $r->nombre_personnes }}</b></div>
                    <div><span class="petit">Nuits</span><b>{{ $r->date_arrivee->diffInDays($r->date_depart) }}</b></div>
                </div>
                @if($litigeOuvert)<x-ui.alerte type="attention">Vous avez signalé un problème : l'équipe GESIMO l'étudie et vous répond sous 48 h.</x-ui.alerte>@endif
            </section>
            @if($contactVisible)
                <section class="carte-p"><h2>Le gérant</h2>
                    <div class="coord"><div><x-ui.icone nom="compte" classe="ic" /><span>{{ $res->gerant->nom }}</span></div><div><x-ui.icone nom="tel" classe="ic" /><span>{{ \App\Noyau\Services\Telephone::afficher($res->gerant->telephone) }}</span></div>
                        @if($res->latitude !== null)<div><x-ui.icone nom="pin" classe="ic" /><span><a href="https://www.google.com/maps?q={{ $res->latitude }},{{ $res->longitude }}" target="_blank" rel="noopener">Voir sur la carte</a></span></div>@endif</div>
                </section>
            @endif
            <section class="carte-p"><h2>Suivi</h2>
                <ol class="suivi">@foreach($etapes as [$libelle, $fait])<li class="{{ $fait ? 'fait' : '' }}">{{ $libelle }}</li>@endforeach</ol>
            </section>
        </div>
        <aside class="col-d">
            <section class="carte-p resa">
                <h2>Paiement</h2>
                <div class="ligne"><span>Total du séjour</span><b>{{ $fr($r->montant_total) }}</b></div>
                <div class="ligne"><span>Payé en ligne</span><b>{{ $fr($paye) }}</b></div>
                @if($r->solde > 0 && $contactVisible)<div class="ligne"><span>À régler au gérant à l'arrivée</span><b>{{ $fr($r->solde) }}</b></div>@endif
                @if($r->statut === $S::EnAttente)<a href="{{ route('payer', $r) }}" data-reseau class="btn-pl pleine">Payer {{ $fr($r->acompte) }}</a>@endif
                @if($paye > 0)<a href="{{ route('reservation.recu', $r) }}" data-reseau target="_blank" class="btn-pl pleine">Télécharger le reçu</a>@endif
                @if($r->statut === $S::Confirmee)<a href="{{ route('reservation.annuler', $r) }}" data-reseau class="btn-sec pleine rouge-b2">Annuler</a>@endif
                @if($r->statut === $S::Terminee)
                    @if($avis)
                        <x-ui.alerte type="succes">Merci pour votre avis : <x-ui.etoiles :note="$avis->note" /></x-ui.alerte>
                    @else
                        <a href="{{ route('reservation.avis', $r) }}" data-reseau class="btn-pl pleine">Donner mon avis</a>
                    @endif
                @elseif(in_array($r->statut, [$S::Confirmee, $S::ArriveeEnregistree], true))
                    <p class="petit" style="margin:0">Après le séjour, vous pourrez donner votre avis ici.</p>
                @endif
                @if(! $litigeOuvert && in_array($r->statut, [$S::Confirmee, $S::ArriveeEnregistree, $S::Terminee, $S::Annulee, $S::Litige], true) && $r->origine !== 'hors_plateforme')
                    <a href="{{ route('reservation.probleme', $r) }}" data-reseau class="lien-l rouge"><x-ui.icone nom="alerte" classe="ic" /> Signaler un problème</a>
                @endif
            </section>
        </aside>
    </div>
</div>
