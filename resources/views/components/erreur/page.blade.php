@props(['code', 'titre', 'icone' => '🧭', 'ton' => 'o', 'statique' => false])
@php
    // Une page « statique » (500, 503) ne touche ni la base de données, ni la session, ni les réglages :
    // elle s'affiche même quand la plateforme est en panne.
    $menuSite = $statique ? [] : \App\Noyau\Services\Navigation::site();
    $estActif = fn () => false;
    $utilisateur = $statique ? null : auth()->user();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
@if($statique)
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FF6A00">
    <title>{{ $titre }} — Avelis Séjours</title>
    @vite(['resources/css/app.css'])
@else
    @include('layouts.partiels.tete')
@endif
</head>
<body class="charte site min-h-screen">
@if($statique)
    <header class="site-h"><div class="cont h-in">
        <a class="logo" href="/"><span class="logo-g" aria-hidden="true">A</span><span>Avelis <b>Séjours</b></span></a>
    </div></header>
@else
    @include('layouts.partiels.entete-site')
@endif
    <main class="page-courante">
        <x-auth.carte :icone="$icone" :ton="$ton" :code="$code">
            <h1>{{ $titre }}</h1>
            {{ $slot }}
            @isset($actions)<div class="en-actions">{{ $actions }}</div>@endisset
        </x-auth.carte>
    </main>
@if($statique)
    <footer class="site-f"><div class="cont"><div class="f-bas"><span>© {{ date('Y') }} Avelis Séjours</span></div></div></footer>
@else
    @include('layouts.partiels.pied-site')
@endif
</body>
</html>
