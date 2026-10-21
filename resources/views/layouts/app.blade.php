@php
    use App\Noyau\Services\Navigation;
    $utilisateur = auth()->user();
    $routeCourante = request()->route()?->getName();
    $habillage = Navigation::habillage($utilisateur, $routeCourante);
    $estActif = fn (array $m) => in_array($routeCourante, $m['actif'], true);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
@include('layouts.partiels.tete')
</head>
@if($habillage === 'site')
@php
    $menuSite = Navigation::site();
    $pleine = in_array($routeCourante, Navigation::PAGES_PLEINES, true);
    $menuBas = $utilisateur ? Navigation::menu($utilisateur) : null;
@endphp
<body class="site min-h-screen {{ $utilisateur ? 'bas-site' : 'bas-visiteur' }}">
    @include('layouts.partiels.entete-site')
    <main class="page-courante">
        @if($pleine){{ $slot }}@else<div class="cont" style="padding-top:22px;padding-bottom:36px">{{ $slot }}</div>@endif
    </main>
    @include('layouts.partiels.pied-site')
    @php $barre = $menuBas ?? Navigation::menu(null); @endphp
    <nav class="barre-bas {{ $utilisateur ? '' : 'barre-bas-visiteur' }}" aria-label="Menu de l'application">
        @foreach($barre as $m)
            <a href="{{ route($m['route']) }}" class="{{ $estActif($m) ? 'on' : '' }}" @if($estActif($m)) aria-current="page" @endif><x-ui.icone :nom="$m['icone']" /><span>{{ $m['libelle'] }}</span></a>
        @endforeach
    </nav>
</body>
@elseif($habillage === 'admin')
@php
    $menuAdmin = Navigation::admin($utilisateur);
    $parRoute = collect($menuAdmin)->keyBy('route');
    $bas = collect(Navigation::ADMIN_BAS)->map(fn ($r) => $parRoute[$r] ?? null)->filter()->values();
    $plus = collect($menuAdmin)->reject(fn ($m) => in_array($m['route'], Navigation::ADMIN_BAS, true))->values();
    $plusActif = $plus->contains(fn ($m) => $estActif($m)) || in_array($routeCourante, ['compte', 'profil'], true);
    $libelleBas = ['admin.tableau' => 'Accueil', 'admin.paiements' => 'Argent'];
@endphp
<body class="min-h-screen">
    <div class="adm-cadre">
        <aside class="adm-side" aria-label="Menu de l'administration">
            <x-ui.logo :clair="true" />
            <nav>
                @foreach($menuAdmin as $m)
                    <a href="{{ route($m['route']) }}" class="{{ $estActif($m) ? 'on' : '' }}" @if($estActif($m)) aria-current="page" @endif><x-ui.icone :nom="$m['icone']" classe="ic" /><span>{{ $m['libelle'] }}</span></a>
                @endforeach
            </nav>
            <div class="adm-moi">
                <span class="avatar">{{ mb_strtoupper(mb_substr($utilisateur->nom, 0, 1)) }}</span>
                <div><b>{{ $utilisateur->nom }}</b><small>{{ $utilisateur->estSuperAdmin() ? 'Super-administrateur' : 'Administrateur' }}</small>
                    <a href="{{ route('compte') }}">Mon compte</a> ·
                    <form method="POST" action="{{ route('deconnexion') }}" class="inline">@csrf<button>Se déconnecter</button></form></div>
            </div>
        </aside>
        <div>
            <header class="adm-haut"><x-ui.logo :clair="true" /><span class="max-w-[55%] truncate text-[15px] font-semibold">{{ $title ?? '' }}</span></header>
            <main class="adm-main">{{ $slot }}</main>
        </div>
    </div>
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
    <nav class="barre-bas adm-bas" aria-label="Menu principal">
        @foreach($bas as $m)
            <a href="{{ route($m['route']) }}" class="{{ $estActif($m) ? 'on' : '' }}" @if($estActif($m)) aria-current="page" @endif><x-ui.icone :nom="$m['icone']" /><span>{{ $libelleBas[$m['route']] ?? $m['libelle'] }}</span></a>
        @endforeach
        <label for="plus-admin" class="{{ $plusActif ? 'on' : '' }}"><x-ui.icone nom="plus-menu" /><span>Plus</span></label>
    </nav>
</body>
@else
@php $menu = Navigation::menu($utilisateur); @endphp
<body class="min-h-screen">
    {{-- Ordinateur : barre du haut, pleine largeur --}}
    <header class="sticky top-0 z-30 hidden h-16 items-center gap-2 border-b border-trait bg-white px-8 lg:flex">
        <a href="{{ route('accueil') }}" class="mr-6 flex items-center gap-2 text-xl font-extrabold text-marque-600">
            @if($logo = \App\Parametres\Services\Marque::logoUrl())<img src="{{ $logo }}" alt="" class="h-8 w-auto rounded">@else<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-marque-600 text-base text-white">G</span>@endif {{ \Illuminate\Support\Str::limit(\App\Parametres\Services\Marque::nom(), 24) }}
        </a>
        @foreach($menu as $m)
            <a href="{{ route($m['route']) }}" class="flex items-center gap-2 rounded-xl px-4 py-2 text-[15px] font-semibold {{ $estActif($m) ? 'bg-marque-50 text-marque-600' : 'text-gris-doux hover:bg-fond' }}">
                <x-ui.icone :nom="$m['icone']" classe="h-5 w-5" /> {{ $m['libelle'] }}
            </a>
        @endforeach
        <div class="ml-auto flex items-center gap-3 text-sm text-gris-doux">
            <span>{{ $utilisateur->nom }}</span>
            <form method="POST" action="{{ route('deconnexion') }}">@csrf<button class="rounded-xl px-3 py-2 font-semibold hover:bg-fond">Se déconnecter</button></form>
        </div>
    </header>

    {{-- Téléphone : barre du haut simple --}}
    <header class="sticky top-0 z-30 flex h-14 items-center justify-between bg-gradient-to-r from-marque-600 to-marque-500 px-4 text-white lg:hidden">
        <a href="{{ route('accueil') }}" class="text-lg font-extrabold">{{ \Illuminate\Support\Str::limit(\App\Parametres\Services\Marque::nom(), 16) }}</a>
        <span class="max-w-[60%] truncate text-[15px] font-semibold">{{ $title ?? '' }}</span>
    </header>

    <main class="mx-auto w-full max-w-[1800px] px-4 pb-28 pt-4 lg:px-10 lg:pb-12 lg:pt-8">
        {{ $slot }}
        <footer class="mt-10 flex flex-wrap justify-center gap-x-5 gap-y-1 border-t border-trait pt-4 text-[13px] text-gris-doux">
            <a class="py-2 hover:underline" href="{{ route('a-propos') }}">À propos</a>
            <a class="py-2 hover:underline" href="{{ route('faq') }}">Questions fréquentes</a>
            <a class="py-2 hover:underline" href="{{ route('contact') }}">Contact</a>
            <a class="py-2 hover:underline" href="{{ route('conditions') }}">Conditions</a>
            <a class="py-2 hover:underline" href="{{ route('confidentialite') }}">Confidentialité</a>
            <a class="py-2 hover:underline" href="{{ route('mentions-legales') }}">Mentions légales</a>
            <a class="py-2 hover:underline" href="{{ route('devenir-partenaire') }}">Devenir gérant partenaire</a>
        </footer>
    </main>

    {{-- Téléphone : barre d'onglets en bas --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-flow-col auto-cols-fr border-t border-trait bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgba(27,36,48,.07)] lg:hidden" aria-label="Menu principal">
        @foreach($menu as $m)
            <a href="{{ route($m['route']) }}" class="flex min-h-[60px] flex-col items-center justify-center gap-0.5 px-1 pb-1 pt-2 text-[11.5px] font-semibold {{ $estActif($m) ? 'text-marque-600' : 'text-gris-doux' }}" @if($estActif($m)) aria-current="page" @endif>
                <x-ui.icone :nom="$m['icone']" />
                <span>{{ $m['libelle'] }}</span>
            </a>
        @endforeach
    </nav>
</body>
@endif
</html>
