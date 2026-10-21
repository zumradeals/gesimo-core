@php $u = auth()->user(); @endphp
<input type="checkbox" id="menu-site" class="burger-cb" aria-label="Ouvrir le menu">
<header class="site-h">
    <div class="cont h-in">
        <x-ui.logo />
        <nav class="h-nav" aria-label="Menu principal">
            @foreach($menuSite as $m)
                <a href="{{ route($m['route']) }}" class="{{ in_array($routeCourante, $m['actif'], true) ? 'on' : '' }}" @if(in_array($routeCourante, $m['actif'], true)) aria-current="page" @endif>{{ $m['libelle'] }}</a>
            @endforeach
        </nav>
        <div class="h-droite">
            @if($u)
                <a href="{{ $u->estAdmin() ? route('admin.tableau') : route('compte') }}" class="lien-co">{{ $u->estAdmin() ? 'Administration' : 'Mon compte' }}</a>
                <form method="POST" action="{{ route('deconnexion') }}">@csrf<button class="btn-sec">Se déconnecter</button></form>
            @else
                <a href="{{ route('connexion') }}" class="lien-co">Se connecter</a>
                <a href="{{ route('inscription') }}" class="btn-pl">Créer un compte</a>
            @endif
        </div>
        <label for="menu-site" class="burger" aria-label="Menu"><x-ui.icone nom="menu" classe="ic" /></label>
    </div>
    <div class="m-panel">
        <div class="m-menu cont">
            @foreach($menuSite as $m)
                <a href="{{ route($m['route']) }}" class="{{ in_array($routeCourante, $m['actif'], true) ? 'on' : '' }}">{{ $m['libelle'] }}</a>
            @endforeach
            <div class="m-btns">
                @if($u)
                    <a href="{{ $u->estAdmin() ? route('admin.tableau') : route('compte') }}" class="btn-pl">{{ $u->estAdmin() ? 'Administration' : 'Mon compte' }}</a>
                    <form method="POST" action="{{ route('deconnexion') }}">@csrf<button class="btn-sec pleine">Se déconnecter</button></form>
                @else
                    <a href="{{ route('inscription') }}" class="btn-pl">Créer un compte</a>
                    <a href="{{ route('connexion') }}" class="btn-sec">Se connecter</a>
                @endif
            </div>
        </div>
    </div>
</header>
