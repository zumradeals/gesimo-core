@php
    use App\Noyau\Services\Telephone;
    use App\Sejours\Services\Itineraire;
    $gerant = $res->gerant;
    $liens = Itineraire::liens($res);
    $lieu = collect([$res->adresse, $res->quartier, $res->commune])->filter()->implode(', ');
@endphp
<div class="mr-page ev-page">
    <p class="fil"><a href="{{ route('reservation.detail', $r) }}">‹ Ma réservation n° {{ $r->id }}</a></p>
    <div class="mr-entete"><div><h1>Comment venir</h1><p class="petit">{{ $r->unite->nom }} · {{ $res->nom }} · arrivée le {{ $r->date_arrivee->translatedFormat('j M') }} à partir de {{ \App\Sejours\Services\ReglesSejour::heureLisible(\App\Sejours\Services\ReglesSejour::heureArrivee()) }}</p></div></div>
    @if(! $visible)
        <section class="mr-carte gq-attente"><div class="ev-gros-ico">🔒</div><h2>Les indications vous seront données après la confirmation de votre réservation.</h2><p class="petit" style="margin:0">Payez l’acompte pour garder vos dates : l’adresse, l’itinéraire et le numéro du gérant s’afficheront ici.</p><a href="{{ route('payer', $r) }}" class="btn-pl">Payer {{ \App\Noyau\Services\Format::fcfa($r->acompte) }}</a></section>
    @else
    <div class="ev-cols">
        <div class="mr-col">
            @if($liens !== [])
                <section class="mr-carte" x-data="{ choix: false }">
                    <div class="gq-carte" aria-hidden="true"><span><b>⌂</b></span></div>
                    <a href="{{ $liens['google'] }}" target="_blank" rel="noopener" class="btn-pl grand pleine" x-on:click="choix = true">📍 Me conduire à la résidence</a>
                    <div class="gq-apps" x-show="choix" x-cloak><a href="{{ $liens['google'] }}" target="_blank" rel="noopener">Google Maps</a><a href="{{ $liens['waze'] }}" target="_blank" rel="noopener">Waze</a><a href="{{ $liens['plans'] }}" target="_blank" rel="noopener">Plans (iPhone)</a></div>
                    <p class="petit" style="margin:0">Le bouton ouvre l’application de cartes de votre téléphone avec la résidence comme destination. Touchez-le : si une autre application vous convient mieux, elle vous est proposée juste en dessous.</p>
                </section>
            @else
                <x-ui.alerte type="attention">Le gérant n’a pas encore indiqué la position sur la carte. Suivez les repères ci-dessous ou appelez-le.</x-ui.alerte>
            @endif
            <section class="mr-carte"><h2>Les repères du gérant</h2>
                <div class="ligne"><span>Adresse</span><b style="text-align:right">{{ $lieu ?: $res->commune }}</b></div>
                @if($res->reperes)<p style="margin:0">« {{ $res->reperes }} »</p>@else<p class="petit" style="margin:0">Le gérant n’a pas encore écrit de repères : appelez-le si besoin.</p>@endif
            </section>
        </div>
        <aside class="mr-col"><section class="mr-carte"><h2>Besoin d’aide pour arriver ?</h2>
            <a href="tel:{{ $gerant->telephone }}" class="btn-pl pleine">📞 Appeler le gérant · {{ Telephone::afficher($gerant->telephone) }}</a>
            @if($liens !== [])<a href="{{ Itineraire::lienWhatsapp($res) }}" target="_blank" rel="noopener" class="btn-sec pleine">💬 Envoyer l’adresse à mon chauffeur (WhatsApp)</a><p class="petit" style="margin:0">Un message prêt à envoyer : adresse, repères et lien de la carte.</p>@endif
        </section></aside>
    </div>
    @endif
</div>
