@php
    use App\Noyau\Services\Telephone;
    $estClient = $utilisateur->aRole(\App\Noyau\Enums\Role::Client);
    $estGerant = $utilisateur->aRole(\App\Noyau\Enums\Role::Gerant);
    $prenom = \Illuminate\Support\Str::of($utilisateur->nom)->before(' ');
    $fcfa = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $jours = $prochain ? (int) now()->startOfDay()->diffInDays($prochain->date_arrivee->startOfDay(), false) : null;
    $repere = match (true) {
        $jours === null || $jours < 0 => null,
        $jours === 0 => 'Arrivée aujourd\'hui',
        $jours === 1 => 'Arrivée demain',
        default => "Arrivée dans {$jours} jours",
    };
@endphp
<div class="mr-page ev-page">
    <div class="mr-entete">
        <div>
            <h1>Bonjour {{ $prenom }}</h1>
            <p class="petit">{{ Telephone::afficher($utilisateur->telephone) }} · {{ $utilisateur->email }} · @foreach($utilisateur->roles as $r)<span class="mr-badge ev-role">{{ $r->role->libelle() }}</span> @endforeach</p>
        </div>
        @if($estClient)<a href="{{ route('recherche') }}" class="btn-pl">Chercher un logement</a>@endif
    </div>

    @if($aFaire)
        @php $f = $aFaire['reservation']; @endphp
        <section class="mr-etape" aria-label="À faire en premier">
            @if($aFaire['type'] === 'paiement')
                <span class="mr-surtitre">À faire en premier</span>
                <h2>Payez pour garder vos dates</h2>
                <p>{{ $f->unite->nom }}, {{ $f->unite->residence->commune }} : vos dates sont gardées encore <b>{{ $f->expire_le->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</b>. Passé ce délai, elles sont libérées.</p>
                <a href="{{ route('payer', $f) }}" class="btn-pl grand">Payer {{ $fcfa($f->acompte) }}</a>
            @elseif($aFaire['type'] === 'piece')
                <span class="mr-surtitre">À faire en premier</span>
                <h2>Ajoutez votre pièce d'identité</h2>
                <p>Le gérant de {{ $f->unite->residence->nom }} en a besoin pour vous accueillir. Vous ne le ferez qu'une fois pour toutes vos réservations.</p>
                <a href="{{ route('reservation.detail', $f) }}#piece-identite" class="btn-pl grand">Ajouter ma pièce d'identité</a>
            @else
                <span class="mr-surtitre">Votre avis compte</span>
                <h2>Comment s'est passé votre séjour à {{ $f->unite->residence->commune }} ?</h2>
                <p>Votre avis aide les prochains voyageurs à choisir en confiance.</p>
                <a href="{{ route('reservation.avis', $f) }}" class="btn-pl grand">Donner mon avis</a>
            @endif
        </section>
    @endif

    <div class="ev-cols">
        <div class="mr-col">
            @if($estClient)
                <section class="mr-carte">
                    <h2>Votre prochain séjour</h2>
                    @if($prochain)
                        @php $u = $prochain->unite; $res = $u->residence; @endphp
                        <article class="ev-sejour">
                            <div class="mr-photo"><x-ui.photo :photo="$u->photos->first() ?? $res->photos->first()" classe="h-[110px]" :alt="'Photo de '.$u->nom" /></div>
                            <div class="mr-corps">
                                <b style="font-size:18px">{{ $u->nom }}</b>
                                <span class="petit">{{ $res->nom }} · {{ $res->commune }}@if($res->quartier), {{ $res->quartier }}@endif</span>
                                <span>{{ $prochain->date_arrivee->translatedFormat('j M') }} → {{ $prochain->date_depart->translatedFormat('j M Y') }} · {{ $prochain->date_arrivee->diffInDays($prochain->date_depart) }} nuit(s)</span>
                                <span class="petit">Gérant : {{ $res->gerant->nom }} · {{ Telephone::afficher($res->gerant->telephone) }}</span>
                                <span><x-ui.badge-statut :statut="$prochain->statut" />@if($repere) <b class="mr-compte">📅 {{ $repere }}</b>@endif</span>
                            </div>
                        </article>
                        <a href="{{ route('reservation.detail', $prochain) }}" class="btn-pl">Voir ma réservation</a>
                    @else
                        <p class="petit" style="margin:0">Vous n'avez pas encore de séjour à venir. Trouvez votre prochain logement.</p>
                        <a href="{{ route('recherche') }}" class="btn-pl">Chercher un logement</a>
                    @endif
                </section>
            @endif
            @if($estGerant && ! $estClient)
                <section class="mr-carte"><h2>Votre activité de gérant</h2>
                    <p class="petit" style="margin:0">Retrouvez vos arrivées, votre calendrier et vos réservations dans l'espace gérant.</p>
                    <a href="{{ route('gerant.arrivees') }}" class="btn-pl">Ouvrir l'espace gérant</a></section>
            @endif
        </div>
        @if($estClient)
            <div class="mr-col">
                <section class="mr-carte"><h2>À faire</h2>
                    @forelse($avisADonner as $a)
                        <div class="ev-ligne"><span><b>Donner votre avis</b><br><span class="petit">« {{ $a->unite->nom }} » · séjour terminé le {{ $a->date_depart->translatedFormat('j F') }}</span></span><a href="{{ route('reservation.avis', $a) }}" class="mr-lien">Donner</a></div>
                    @empty
                        <p class="petit" style="margin:0">Rien à faire pour le moment.</p>
                    @endforelse
                </section>
            </div>
        @endif
        <aside class="mr-col">
            @if($estClient)
                <div class="ev-kpi"><div><b>{{ $nombreAVenir }}</b><span class="petit">séjour(s) à venir</span></div><div><b>{{ $nombreSejours }}</b><span class="petit">séjour(s) terminé(s)</span></div></div>
            @endif
            <section class="mr-carte ev-raccourcis"><h2>Raccourcis</h2>
                @if($estClient)<a href="{{ route('mes-reservations') }}"><x-ui.icone nom="liste" classe="ic" /> Mes réservations</a>@endif
                <a href="{{ route('profil') }}"><x-ui.icone nom="compte" classe="ic" /> Mon profil</a>
                @if($estGerant)
                    <a href="{{ route('gerant.personnel') }}"><x-ui.icone nom="equipe" classe="ic" /> Mon personnel</a>
                    <a href="{{ route('gerant.mises-en-avant') }}"><x-ui.icone nom="etoile" classe="ic" /> Mises en avant</a>
                    <a href="{{ route('gerant.aide') }}"><x-ui.icone nom="aide" classe="ic" /> Aide du gérant</a>
                @endif
                <a href="{{ route('faq') }}"><x-ui.icone nom="aide" classe="ic" /> Aide et questions</a>
                <form method="POST" action="{{ route('deconnexion') }}">@csrf<button type="submit" class="rouge"><x-ui.icone nom="sortie" classe="ic" /> Se déconnecter</button></form>
            </section>
        </aside>
    </div>
</div>
