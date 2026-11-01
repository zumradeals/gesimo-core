@php
    use App\Parametres\Services\Marque;
    $nom = Marque::nom();
    $image = Marque::imageAccueilUrl() ?: asset('images/sejours/salon-balcon.webp');
@endphp
<div class="accueil-v3">
    <div class="ah-entree">
    <section class="cont ah-hero" aria-labelledby="titre-accueil">
        <div class="ah-hero-texte">
            <span class="surtitre">Votre séjour en Côte d’Ivoire</span>
            <h1 id="titre-accueil">Un lieu à vous.<br>Le temps d’un séjour.</h1>
            <p>{{ Marque::slogan() ?: 'Trouvez votre résidence meublée, avec des informations claires avant de réserver.' }}</p>
        </div>
        <div class="ah-hero-visuel"><img src="{{ $image }}" alt="Intérieur illustratif d’une résidence meublée" width="1536" height="1024" fetchpriority="high" decoding="async"></div>
    </section>
    <div class="cont ah-recherche-cont">
        <form class="ah-recherche" method="get" action="{{ route('recherche') }}" aria-label="Rechercher un séjour">
            <h2>Où souhaitez-vous séjourner ?</h2>
            <div class="ah-recherche-champs">
                <label class="ah-commune" for="accueil-commune"><x-ui.icone nom="pin" classe="ic" /><span>Commune</span><select id="accueil-commune" name="commune"><option value="">Toutes les communes</option>@foreach($toutesCommunes as $c)<option>{{ $c }}</option>@endforeach</select></label>
                <label for="accueil-arrivee"><x-ui.icone nom="calendrier" classe="ic" /><span>Arrivée</span><input id="accueil-arrivee" type="date" name="arrivee" min="{{ now()->toDateString() }}" required></label>
                <label for="accueil-depart"><x-ui.icone nom="calendrier" classe="ic" /><span>Départ</span><input id="accueil-depart" type="date" name="depart" min="{{ now()->addDay()->toDateString() }}" required></label>
                <label class="ah-voyageurs" for="accueil-personnes"><x-ui.icone nom="compte" classe="ic" /><span>Voyageurs</span><select id="accueil-personnes" name="personnes">@foreach([1, 2, 3, 4, 5, 6, 8, 10, 12] as $n)<option value="{{ $n }}" @selected($n === 2)>{{ $n }} {{ $n === 1 ? 'personne' : 'personnes' }}</option>@endforeach</select></label>
                <button class="btn-pl" type="submit"><span>Rechercher un logement</span><span aria-hidden="true">→</span></button>
            </div>
            <p class="ah-recherche-note">Choisissez vos dates pour consulter les logements disponibles et le montant de votre séjour.</p>
        </form>
    </div>
    <div class="cont ah-reassurance">
        <div><x-ui.icone nom="bouclier" classe="ic" /><p><b>Identité du gérant contrôlée</b><span>Sa pièce d’identité est validée avant publication.</span></p></div>
        <div><x-ui.icone nom="prix" classe="ic" /><p><b>Total affiché avant paiement</b><span>Consultez le montant du séjour avant de réserver.</span></p></div>
        <div><x-ui.icone nom="carte" classe="ic" /><p><b>Mobile Money ou carte</b><span>Les moyens disponibles sont précisés au paiement.</span></p></div>
    </div>

    </div>

    @if($unites->isNotEmpty())
    <section class="ah-section" aria-labelledby="offres-titre"><div class="cont">
        <div class="ah-section-titre"><div><span class="surtitre">Logements à la une</span><h2 id="offres-titre">Posez vos valises,  sentez-vous chez vous.</h2><p>Des logements mis en avant par leurs gérants, à découvrir pour votre prochain séjour.</p></div><a href="{{ route('recherche') }}" class="lien-fl">Voir les logements <span aria-hidden="true">→</span></a></div>
        <div class="ah-offres ah-offres-reelles">
        @foreach($unites as $u)
            <x-ui.carte-logement :u="$u" :en-avant="$enAvant" />
        @endforeach
        </div>
    </div></section>
    @endif

    @if($ordinaires->isNotEmpty())
    <section class="ah-section ah-ordinaires" aria-labelledby="ordinaires-titre"><div class="cont">
        <div class="ah-section-titre"><div><span class="surtitre">Découvrez nos logements</span><h2 id="ordinaires-titre">À chaque séjour, son cadre.</h2></div><a href="{{ route('recherche') }}" class="lien-fl">Explorer les logements <span aria-hidden="true">→</span></a></div>
        <div class="ah-offres ah-offres-reelles">@foreach($ordinaires as $u)<x-ui.carte-logement :u="$u" />@endforeach</div>
    </div></section>
    @endif

    @if($communes->isNotEmpty())
    <section class="ah-section ah-destinations" aria-labelledby="communes-titre">
        <div class="cont" x-data="carrouselCommunes">
            <div class="ah-section-titre"><div><h2 id="communes-titre">La Côte d’Ivoire, à votre rythme.</h2></div><div class="ah-carrousel-commandes" x-cloak><button type="button" @click="deplacer(-1)" :disabled="debut" aria-label="Communes précédentes" aria-controls="carrousel-communes">←</button><button type="button" @click="deplacer(1)" :disabled="fin" aria-label="Communes suivantes" aria-controls="carrousel-communes">→</button></div></div>
            <div class="ah-communes" id="carrousel-communes" x-ref="piste" @scroll.passive.debounce.80ms="actualiser()" role="region" aria-label="Communes à découvrir" tabindex="0">
                @foreach($communes as $c)
                    <a class="ah-commune-carte" href="{{ route('recherche', ['commune' => $c->commune]) }}" aria-label="Découvrir les logements à {{ $c->commune }}">
                        <div class="ah-commune-photo"><x-ui.photo :photo="$c->photoApercu" classe="h-full w-full" :plat="true" :alt="'Aperçu d’un logement à '.$c->commune" sizes="(max-width: 639px) 100vw, (max-width: 1023px) 50vw, 400px" /></div>
                        <div class="ah-commune-corps"><span class="ah-commune-lieu"><x-ui.icone nom="pin" classe="ic" /><h3>{{ $c->commune }}</h3></span><p>{{ $c->nombre }} logement{{ $c->nombre > 1 ? 's' : '' }} publié{{ $c->nombre > 1 ? 's' : '' }}</p><span class="ah-commune-action">Découvrir les logements <span aria-hidden="true">→</span></span></div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="ah-section" id="engagements" aria-labelledby="engagements-titre"><div class="cont ah-confiance"><div class="ah-confiance-visuel"><img src="{{ asset('images/sejours/salon-balcon.webp') }}" alt="Intérieur illustratif d’un séjour meublé chaleureux" width="768" height="700" loading="lazy" decoding="async"></div><div class="ah-confiance-texte"><span class="surtitre">La confiance se construit</span><h2 id="engagements-titre">Sachez ce que vous réservez.</h2>
        <ol><li><span>1</span><div><h3>Un gérant identifié</h3><p>Sa pièce d’identité est contrôlée avant la publication de ses résidences. L’annonce fait elle aussi l’objet d’une validation par Avelis.</p></div></li><li><span>2</span><div><h3>Des informations lisibles</h3><p>Photos, équipements et localisation à consulter avant votre réservation.</p></div></li><li><span>3</span><div><h3>Vos conditions avant de payer</h3><p>Consultez le montant total et les conditions d’annulation avant confirmation. Vous choisissez en connaissant les règles de votre séjour.</p></div></li></ol><a href="{{ route('a-propos') }}" class="lien-fl">Comprendre nos engagements <span aria-hidden="true">→</span></a>
    </div></div></section>

    <section class="ah-section ah-comment" id="comment" aria-labelledby="comment-titre"><div class="cont"><h2 id="comment-titre">De la recherche à l’arrivée, simplement.</h2><div class="ah-etapes"><div><span class="ah-numero" aria-hidden="true">1</span><div><h3>Choisissez votre séjour</h3><p>Indiquez la commune, les dates et les voyageurs. Comparez les logements disponibles et le total du séjour.</p></div></div><div><span class="ah-numero" aria-hidden="true">2</span><div><h3>Réservez et payez</h3><p>Selon l’offre, réglez un acompte ou la totalité. Vos dates sont gardées {{ $blocage }} pendant le paiement.</p></div></div><div><span class="ah-numero" aria-hidden="true">3</span><div><h3>Préparez votre arrivée</h3><p>Après confirmation, retrouvez votre réservation et les coordonnées du gérant pour organiser votre accueil.</p></div></div></div></div></section>

    @if($avis->isNotEmpty())
    <section class="ah-section"><div class="cont"><div class="ah-section-titre"><div><span class="surtitre">Leurs séjours, leurs mots</span><h2>Ce que disent nos voyageurs</h2></div></div><div class="ah-offres">@foreach($avis as $a)@php $mots = preg_split('/\s+/', trim($a->client->nom)); $court = $mots[0].(count($mots) > 1 ? ' '.mb_strtoupper(mb_substr(end($mots), 0, 1)).'.' : ''); @endphp<blockquote class="avis"><div class="etoiles" aria-label="{{ $a->note }} sur 5">{{ str_repeat('★', $a->note) }}</div><p>« {{ $a->commentaire }} »</p><footer><b>{{ $court }}</b> · séjour à {{ $a->reservation->unite->residence->commune }}</footer></blockquote>@endforeach</div></div></section>
    @endif

    <section class="ah-section ah-partenaires"><div class="cont"><div class="ah-partenaire"><img src="{{ asset('images/sejours/appartement.webp') }}" alt="" width="768" height="512" loading="lazy" decoding="async"><div><h2>Votre résidence mérite d’être découverte.</h2><p>Rejoignez {{ $nom }} et donnez plus de visibilité à votre logement auprès des voyageurs en Côte d’Ivoire.</p><a href="{{ route('devenir-partenaire') }}" class="btn-or">Devenir gérant partenaire <span aria-hidden="true">→</span></a></div></div></div></section>

</div>
