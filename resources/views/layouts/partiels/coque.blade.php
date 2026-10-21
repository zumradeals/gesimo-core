@php
    use App\Noyau\Services\Navigation;
    $coque = Navigation::coque($utilisateur, $habillage);
    $menuLateral = $coque['menu'];
    $parRoute = collect($menuLateral)->keyBy('route');
    $bas = collect($coque['bas'])->map(fn ($r) => $parRoute[$r] ?? null)->filter()->values();
    $plus = collect($menuLateral)->reject(fn ($m) => in_array($m['route'], $coque['bas'], true))->values();
    $routesCompte = $habillage === 'client' ? ['profil'] : ['compte', 'profil'];
    $plusActif = $plus->contains(fn ($m) => $estActif($m)) || in_array($routeCourante, $routesCompte, true);
    $avecPlus = $habillage !== 'client';
    if (! $avecPlus) { $plus = collect(); $plusActif = false; }
    $libelleBas = $coque['libelles'];
    $roleEtiquette = ['admin' => 'Admin', 'gerant' => 'Gérant', 'client' => null][$habillage];
@endphp
<body class="min-h-screen">
    <div class="adm-cadre">
        <aside class="adm-side" aria-label="Menu de l'espace">
            <x-ui.logo :clair="true" />
            @if($roleEtiquette)<span class="adm-role">Espace {{ mb_strtolower($roleEtiquette) }}</span>@endif
            <nav>
                @foreach($menuLateral as $m)
                    <a href="{{ route($m['route']) }}" class="{{ $estActif($m) ? 'on' : '' }}" @if($estActif($m)) aria-current="page" @endif><x-ui.icone :nom="$m['icone']" classe="ic" /><span>{{ $m['libelle'] }}</span></a>
                @endforeach
            </nav>
            <div class="adm-moi">
                <span class="avatar">{{ mb_strtoupper(mb_substr($utilisateur->nom, 0, 1)) }}</span>
                <div><b>{{ $utilisateur->nom }}</b><small>{{ $coque['role'] }}</small>
                    @if($habillage !== 'client')<a href="{{ route('compte') }}">Mon compte</a> · @endif
                    <form method="POST" action="{{ route('deconnexion') }}" class="inline">@csrf<button>Se déconnecter</button></form></div>
            </div>
        </aside>
        <div>
            <header class="adm-haut"><x-ui.logo :clair="true" /><span class="max-w-[55%] truncate text-[15px] font-semibold">{{ $title ?? '' }}</span></header>
            <main class="adm-main">{{ $slot }}</main>
        </div>
    </div>
    @if($avecPlus)
        <input type="checkbox" id="plus-admin" class="plus-cb" aria-label="Plus de rubriques">
        <div class="plus-feuille">
            <label for="plus-admin" class="absolute inset-0" aria-label="Fermer"></label>
            <div class="plus-liste">
                @foreach($plus as $m)
                    <a href="{{ route($m['route']) }}"><x-ui.icone :nom="$m['icone']" classe="ic" />{{ $m['libelle'] }}</a>
                @endforeach
                <a href="{{ route('compte') }}"><x-ui.icone nom="compte" classe="ic" />Mon compte</a>
                <a href="{{ route('accueil') }}"><x-ui.icone nom="recherche" classe="ic" />Voir le site</a>
                <form method="POST" action="{{ route('deconnexion') }}">@csrf<button><x-ui.icone nom="sortie" classe="ic" />Se déconnecter</button></form>
            </div>
        </div>
    @endif
    @if($habillage === 'client')
        @include('layouts.partiels.navigation-mobile-site')
    @else
    <nav class="barre-bas adm-bas" aria-label="Menu principal">
        @foreach($bas as $m)
            <a href="{{ route($m['route']) }}" class="{{ $estActif($m) ? 'on' : '' }}" @if($estActif($m)) aria-current="page" @endif><x-ui.icone :nom="$m['icone']" /><span>{{ $libelleBas[$m['route']] ?? $m['libelle'] }}</span></a>
        @endforeach
        @if($avecPlus)<label for="plus-admin" class="{{ $plusActif ? 'on' : '' }}"><x-ui.icone nom="plus-menu" /><span>Plus</span></label>@endif
    </nav>
    @endif
</body>
