@php
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $u = auth()->user();
    $prenom = \Illuminate\Support\Str::of($u->nom)->explode(' ')->first();
    $total = $nbAValider + $nbLitiges + $nbAlertes;
    $detailTotal = collect([$nbAValider ? $nbAValider.' résidence'.($nbAValider > 1 ? 's' : '') : null, $nbLitiges ? $nbLitiges.' litige'.($nbLitiges > 1 ? 's' : '') : null, $nbAlertes ? $nbAlertes.' alerte'.($nbAlertes > 1 ? 's' : '') : null])->filter()->implode(' · ');
    $signe = fn (int $n) => ($n > 0 ? '▲ +' : ($n < 0 ? '▼ ' : '')).$n;
@endphp
<div style="display:grid;gap:20px">
    <div class="adm-top">
        <div><h1>Bonjour {{ $prenom }} 👋</h1><p class="petit">{{ now()->translatedFormat('l j F Y') }} · {{ $total > 0 ? 'voici ce qui demande votre attention' : 'tout est à jour' }}</p></div>
        <div class="adm-act"><a href="{{ route('admin.journal') }}" class="btn-sec">Journal</a>@can('modifier-parametres')<a href="{{ route('admin.reglages') }}" class="btn-pl">Réglages</a>@endcan</div>
    </div>

    @if($u->estSuperAdmin() && ! \App\Noyau\Services\Installation::estTerminee())
        <x-ui.alerte type="attention">L'installation n'est pas terminée. <a class="font-bold underline" href="{{ route('installation') }}">Terminer l'installation</a></x-ui.alerte>
    @endif

    <div class="kpi">
        <a href="{{ route('admin.controle') }}" class="k"><span class="k-i v"><x-ui.icone nom="alerte" classe="ic" /></span><b>{{ $total }}</b><span>À traiter</span><small>{{ $detailTotal ?: 'rien en attente' }}</small></a>
        <a href="{{ route('admin.reservations') }}" class="k"><span class="k-i b"><x-ui.icone nom="liste" classe="ic" /></span><b>{{ $resa7 }}</b><span>Réservations (7 jours)</span><small class="var {{ $resa7Evolution > 0 ? 'hausse' : ($resa7Evolution < 0 ? 'baisse' : '') }}">{{ $resa7Evolution === 0 ? 'stable vs semaine passée' : $signe($resa7Evolution).' vs semaine passée' }}</small></a>
        <a href="{{ route('admin.paiements') }}" class="k"><span class="k-i o"><x-ui.icone nom="argent" classe="ic" /></span><b>{{ number_format($commissionsSemaine, 0, ',', "\u{202F}") }}</b><span>Commissions (7 jours, FCFA)</span><small class="var {{ ($commissionsEvolution ?? 0) > 0 ? 'hausse' : (($commissionsEvolution ?? 0) < 0 ? 'baisse' : '') }}">{{ $commissionsEvolution === null ? 'pas de comparaison' : ($commissionsEvolution > 0 ? '▲ +' : ($commissionsEvolution < 0 ? '▼ ' : '')).$commissionsEvolution.' % vs semaine passée' }}</small></a>
        <div class="k"><span class="k-i g"><x-ui.icone nom="graphe" classe="ic" /></span><b>{{ $tauxExpiration }} %</b><span>Taux d'expiration (30 jours)</span><small>{{ $tauxAnnulation }} % d'annulation</small></div>
    </div>

    <div class="adm-2">
        <section class="carte-a">
            <h2>À traiter maintenant</h2>
            @if($total === 0)<p class="petit">Rien à traiter pour le moment. 👍</p>@endif
            @foreach($litiges as $l)<div class="tache"><span class="pt-t rouge"></span><div><b>Litige n° {{ $l->id }}</b><small>{{ \Illuminate\Support\Str::limit($l->motif, 60) }} · réservation #{{ $l->reservation_id }}</small></div><a href="{{ route('admin.litiges') }}">Ouvrir</a></div>@endforeach
            @foreach($aValider as $r)<div class="tache"><span class="pt-t or"></span><div><b>{{ $r->nom }}</b><small>Résidence à valider</small></div><a href="{{ route('admin.residence', $r) }}">Ouvrir</a></div>@endforeach
            @foreach($alertes as $a)<div class="tache"><span class="pt-t or"></span><div><b>{{ \App\Paiement\Models\AlerteAdmin::libelleType($a->type) }}</b><small>Alerte argent</small></div><a href="{{ route('admin.controle') }}">Ouvrir</a></div>@endforeach
        </section>
        <section class="carte-a">
            <h2>Réservations par jour <small>(7 derniers jours)</small></h2>
            <div class="barres" role="img" aria-label="Réservations par jour sur les 7 derniers jours">
                @foreach($parJour as $j)<span class="{{ $j['nombre'] === 0 ? 'zero' : '' }}" style="height:{{ max(3, (int) round(100 * $j['nombre'] / $maxJour)) }}%"><i>{{ $j['nombre'] }}</i></span>@endforeach
            </div>
            <div class="jours">@foreach($parJour as $j)<span>{{ $j['jour'] }}</span>@endforeach</div>
            <p class="petit" style="margin-top:8px">Confirmées sur 7 jours : <b>{{ $confirmeesSemaine }}</b> · créées aujourd'hui : <b>{{ $aujourdhui }}</b></p>
        </section>
    </div>

    <section class="carte-a">
        <h2>Dernières réservations</h2>
        @if($dernieres->isEmpty())<p class="petit">Aucune réservation pour le moment.</p>
        @else
            <table class="tab">
                <tr><th>N°</th><th>Client</th><th>Logement</th><th>Dates</th><th>Statut</th><th class="d">Montant</th></tr>
                @foreach($dernieres as $r)
                    <tr><td>#{{ $r->id }}</td><td>{{ $r->client?->nom ?? $r->nom_client }}</td><td>{{ $r->unite->nom }} · {{ $r->unite->residence->nom }}</td><td>{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M') }}</td><td><x-ui.badge-statut :statut="$r->statut" /></td><td class="d">{{ number_format($r->montant_total, 0, ',', "\u{202F}") }}</td></tr>
                @endforeach
            </table>
            <p style="margin:10px 0 0"><a href="{{ route('admin.reservations') }}" class="lien-fl">Toutes les réservations <x-ui.icone nom="fleche" classe="ic" /></a></p>
        @endif
    </section>
</div>
