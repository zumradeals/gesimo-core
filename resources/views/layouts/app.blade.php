@php
    $utilisateur = auth()->user();
    $menu = \App\Noyau\Services\Navigation::menu($utilisateur);
    $routeCourante = request()->route()?->getName();
    $estActif = fn (array $m) => in_array($routeCourante, $m['actif'], true);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <link rel="manifest" href="{{ route('pwa.manifeste') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="/icons/favicon-48.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit(explode(' ', \App\Parametres\Services\Marque::nom())[0], 12, '') }}">
    <title>{{ isset($title) ? $title.' — ' : '' }}{{ \App\Parametres\Services\Marque::nom() }}</title>
    @livewireStyles
    @livewireScriptConfig
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
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
            @if($utilisateur)
                <span>{{ $utilisateur->nom }}</span>
                <form method="POST" action="{{ route('deconnexion') }}">@csrf<button class="rounded-xl px-3 py-2 font-semibold hover:bg-fond">Se déconnecter</button></form>
            @else
                <a href="{{ route('inscription') }}" class="rounded-xl bg-marque-600 px-4 py-2 font-bold text-white">Créer un compte</a>
            @endif
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
</html>
