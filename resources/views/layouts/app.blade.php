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
@endphp
<body class="site min-h-screen {{ $utilisateur ? '' : 'bas-visiteur' }}">
    @include('layouts.partiels.entete-site')
    <main class="page-courante">
        @if($pleine){{ $slot }}@else<div class="cont" style="padding-top:22px;padding-bottom:36px">{{ $slot }}</div>@endif
    </main>
    @include('layouts.partiels.pied-site')
    @unless($utilisateur)
        <nav class="barre-bas barre-bas-visiteur" aria-label="Menu de l'application">
            @foreach(Navigation::visiteur() as $m)
                <a href="{{ route($m['route']) }}" class="{{ $estActif($m) ? 'on' : '' }}" @if($estActif($m)) aria-current="page" @endif><x-ui.icone :nom="$m['icone']" /><span>{{ $m['libelle'] }}</span></a>
            @endforeach
        </nav>
    @endunless
</body>
@else
@include('layouts.partiels.coque')
@endif
</html>
