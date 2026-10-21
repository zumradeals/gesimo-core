@php $u = auth()->user(); @endphp
<div class="site-utilitaire"><div class="cont"><span>Résidences meublées en Côte d’Ivoire</span><a href="{{ route('contact') }}">Besoin d’aide ? Contactez-nous <span aria-hidden="true">↗</span></a></div></div>
<header class="site-h">
    <div class="cont h-in">
        <x-ui.logo />
        <nav class="h-nav" aria-label="Menu principal">
            @foreach($menuSite as $m)
                <a href="{{ route($m['route']).($m['ancre'] ?? '') }}" @if($estActif($m)) class="on" aria-current="page" @endif>{{ $m['libelle'] }}</a>
            @endforeach
        </nav>
        <div class="h-droite">
            @if($u)
                <a href="{{ $u->estAdmin() ? route('admin.tableau') : route('compte') }}" class="lien-co">{{ $u->estAdmin() ? 'Administration' : 'Mon compte' }}</a>
                <form method="POST" action="{{ route('deconnexion') }}">@csrf<button class="btn-sec">Se déconnecter</button></form>
            @else
                <a href="{{ route('connexion') }}" class="lien-co">Se connecter</a>
                <a href="{{ route('inscription') }}" class="btn-sec">Créer un compte</a>
            @endif
        </div>
        <details class="site-menu-mobile" x-data @keydown.escape="$el.removeAttribute('open'); $el.querySelector('summary').focus()" @click.outside="$el.removeAttribute('open')">
            <summary><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg><span>Menu</span></summary>
            <nav aria-label="Menu principal mobile">
                @foreach($menuSite as $m)
                    <a href="{{ route($m['route']) }}" @if($estActif($m)) aria-current="page" @endif>{{ $m['libelle'] }}</a>
                @endforeach
                <a class="site-menu-compte" href="{{ $u ? ($u->estAdmin() ? route('admin.tableau') : route('compte')) : route('connexion') }}">{{ $u ? 'Mon compte' : 'Se connecter' }}</a>
            </nav>
        </details>
    </div>
</header>
