@php $u = auth()->user(); @endphp
<div class="site-utilitaire"><div class="cont"><span>Résidences meublées en Côte d’Ivoire</span><a href="{{ route('contact') }}">Besoin d’aide ? Contactez-nous <span aria-hidden="true">↗</span></a></div></div>
<header class="site-h">
    <div class="cont h-in">
        <x-ui.logo />
        <nav class="h-nav" aria-label="Menu principal">
            <a href="{{ route('recherche') }}" @if(in_array($routeCourante, ['recherche', 'logement', 'hebergeur'])) class="on" aria-current="page" @endif>Logements</a>
            <a href="{{ route('accueil') }}#comment">Comment ça marche</a>
            <a href="{{ route('devenir-partenaire') }}" @if($routeCourante === 'devenir-partenaire') class="on" aria-current="page" @endif>Devenir partenaire</a>
            <a href="{{ route('faq') }}" @if($routeCourante === 'faq') class="on" aria-current="page" @endif>Aide</a>
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
        <a class="aide-mobile" href="{{ route('faq') }}" aria-label="Aide et questions fréquentes"><x-ui.icone nom="aide" classe="ic" /></a>
    </div>
</header>
