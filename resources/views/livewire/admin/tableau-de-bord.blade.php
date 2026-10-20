@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $rien = $nbAValider + $nbLitiges + $nbAlertes === 0; @endphp
<div class="grid gap-4">
    <h1 class="text-2xl font-extrabold">Tableau de bord</h1>
    @if(auth()->user()->estSuperAdmin() && ! \App\Noyau\Services\Installation::estTerminee())
        <x-ui.alerte type="attention">L'installation n'est pas terminée. <a class="font-bold underline" href="{{ route('installation') }}">Terminer l'installation</a></x-ui.alerte>
    @endif
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <a href="{{ route('admin.residences') }}" class="carte"><b class="text-2xl">{{ $nbAValider }}</b><p class="aide">Résidences à valider</p></a>
        <a href="{{ route('admin.litiges') }}" class="carte"><b class="text-2xl">{{ $nbLitiges }}</b><p class="aide">Litiges ouverts</p></a>
        <a href="{{ route('admin.controle') }}" class="carte"><b class="text-2xl">{{ $nbAlertes }}</b><p class="aide">Alertes argent</p></a>
        <a href="{{ route('admin.reservations') }}" class="carte"><b class="text-2xl">{{ $aujourdhui }}</b><p class="aide">Réservations aujourd'hui</p></a>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="carte grid gap-1">
            <h2 class="text-lg font-extrabold">À traiter</h2>
            @if($rien)<p class="text-gris-doux">Tout est à jour. 👍</p>@endif
            @foreach($aValider as $r)<a href="{{ route('admin.residence', $r) }}" class="flex justify-between gap-2 border-b border-dashed border-trait py-2"><span>{{ $r->nom }} — à valider</span><span class="font-bold text-marque-600">Ouvrir</span></a>@endforeach
            @foreach($litiges as $l)<a href="{{ route('admin.litiges') }}" class="flex justify-between gap-2 border-b border-dashed border-trait py-2"><span>Litige n° {{ $l->id }} : {{ \Illuminate\Support\Str::limit($l->motif, 50) }}</span><span class="font-bold text-marque-600">Ouvrir</span></a>@endforeach
            @foreach($alertes as $a)<a href="{{ route('admin.controle') }}" class="flex justify-between gap-2 border-b border-dashed border-trait py-2"><span>{{ \App\Paiement\Models\AlerteAdmin::libelleType($a->type) }}</span><span class="font-bold text-marque-600">Ouvrir</span></a>@endforeach
        </div>
        <div class="carte grid gap-1">
            <h2 class="text-lg font-extrabold">Indicateurs</h2>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Réservations confirmées (7 jours)</span><b>{{ $confirmeesSemaine }}</b></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Taux d'expiration (30 jours)</span><b>{{ $tauxExpiration }} %</b></div>
            <div class="flex justify-between border-b border-dashed border-trait py-2"><span>Taux d'annulation (30 jours)</span><b>{{ $tauxAnnulation }} %</b></div>
            <div class="flex justify-between py-2"><span>Commissions (7 jours)</span><b>{{ $fr($commissionsSemaine) }}</b></div>
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('admin.reservations') }}" class="btn btn-second btn-bloc">Toutes les réservations</a>
        <a href="{{ route('admin.litiges') }}" class="btn btn-second btn-bloc">Litiges</a>
        <a href="{{ route('admin.journal') }}" class="btn btn-second btn-bloc">Journal d'audit</a>
        @can('modifier-parametres')<a href="{{ route('admin.messages') }}" class="btn btn-second btn-bloc">Modèles d'e-mails</a>@endcan
    </div>
</div>
