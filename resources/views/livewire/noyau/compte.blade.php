@php
    use App\Noyau\Services\Telephone;
    $estClient = $utilisateur->aRole(\App\Noyau\Enums\Role::Client);
    $estGerant = $utilisateur->aRole(\App\Noyau\Enums\Role::Gerant);
    $prenom = \Illuminate\Support\Str::of($utilisateur->nom)->before(' ');
@endphp
<div style="display:grid;gap:16px">
    <section class="banniere">
        <div><h1>Bonjour {{ $prenom }} 👋</h1><p>{{ Telephone::afficher($utilisateur->telephone) }} · {{ $utilisateur->email }}</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">@foreach($utilisateur->roles as $r)<span class="etq-b">{{ $r->role->libelle() }}</span>@endforeach</div></div>
        @if($estClient)<a href="{{ route('recherche') }}" class="btn-or">Chercher un logement</a>@endif
    </section>

    <div class="cols">
        <div class="col-g">
            @if($estClient)
                <section class="carte-p">
                    <h2>Votre prochain séjour</h2>
                    @if($prochain)
                        @php $u = $prochain->unite; $res = $u->residence; @endphp
                        <div class="res-item">
                            <div style="width:120px;flex:none"><x-ui.photo :photo="$u->photos->first() ?? $res->photos->first()" classe="h-24" :alt="'Photo de '.$u->nom" /></div>
                            <div class="ri"><b>{{ $u->nom }} · {{ $res->nom }}</b><span class="petit">{{ $res->commune }}@if($res->quartier), {{ $res->quartier }}@endif</span>
                                <span>Arrivée le <b>{{ $prochain->date_arrivee->translatedFormat('l j F') }}</b> · {{ $prochain->date_arrivee->diffInDays($prochain->date_depart) }} nuit(s)</span>
                                <span class="petit">Gérant : {{ $res->gerant->nom }} ({{ Telephone::afficher($res->gerant->telephone) }})</span>
                                <span><x-ui.badge-statut :statut="$prochain->statut" /></span></div>
                        </div>
                        <a href="{{ route('reservation.detail', $prochain) }}" class="btn-pl">Voir ma réservation</a>
                    @else
                        <p class="petit" style="margin:0">Vous n'avez pas encore de séjour à venir. Trouvez votre prochain logement.</p>
                        <a href="{{ route('recherche') }}" class="btn-pl">Chercher un logement</a>
                    @endif
                </section>
                @if($avisADonner->isNotEmpty())
                    <section class="carte-p"><h2>À faire</h2>
                        @foreach($avisADonner as $a)<div class="tache"><span class="pt-t vert"></span><div><b>Donner votre avis</b><small>« {{ $a->unite->nom }} » · séjour terminé le {{ $a->date_depart->translatedFormat('j F') }}</small></div><a href="{{ route('reservation.avis', $a) }}">Donner</a></div>@endforeach
                    </section>
                @endif
            @endif
        </div>
        <aside class="col-d">
            @if($estClient)
                <div class="kpi2"><div class="k"><b>{{ $nombreAVenir }}</b><span>séjour(s) à venir</span></div><div class="k"><b>{{ $nombreSejours }}</b><span>séjour(s) terminé(s)</span></div></div>
            @endif
            <section class="carte-p"><h2>Raccourcis</h2>
                @if($estClient)<a href="{{ route('mes-reservations') }}" class="lien-l"><x-ui.icone nom="liste" classe="ic" /> Mes réservations</a>@endif
                <a href="{{ route('profil') }}" class="lien-l"><x-ui.icone nom="compte" classe="ic" /> Mon profil</a>
                @if($estGerant)
                    <a href="{{ route('gerant.personnel') }}" class="lien-l"><x-ui.icone nom="equipe" classe="ic" /> Mon personnel</a>
                    <a href="{{ route('gerant.mises-en-avant') }}" class="lien-l"><x-ui.icone nom="etoile" classe="ic" /> Mises en avant</a>
                    <a href="{{ route('gerant.aide') }}" class="lien-l"><x-ui.icone nom="aide" classe="ic" /> Aide du gérant</a>
                @endif
                <a href="{{ route('faq') }}" class="lien-l"><x-ui.icone nom="aide" classe="ic" /> Aide et questions</a>
                <form method="POST" action="{{ route('deconnexion') }}">@csrf<button class="lien-l rouge" style="width:100%;background:none;border:0;border-bottom:1px dashed var(--bord);text-align:left;font-size:inherit;cursor:pointer"><x-ui.icone nom="sortie" classe="ic" /> Se déconnecter</button></form>
            </section>
        </aside>
    </div>
</div>
