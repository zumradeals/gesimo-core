@php
    use App\Parametres\Services\Marque;
    $nom = Marque::nom();
    $image = Marque::imageAccueilUrl() ?: asset('images/sejours/salon-balcon.webp');
    $exemples = [
        ['titre' => 'Studio lumineux à Cocody', 'lieu' => 'Cocody', 'details' => '2 voyageurs · Studio', 'prix' => 25000, 'photo' => 'studio.webp'],
        ['titre' => 'Appartement cosy à Marcory', 'lieu' => 'Marcory', 'details' => '4 voyageurs · Appartement', 'prix' => 40000, 'photo' => 'appartement.webp'],
        ['titre' => 'Séjour paisible à Riviera', 'lieu' => 'Cocody · Riviera', 'details' => '4 voyageurs · Appartement', 'prix' => 55000, 'photo' => 'salon-balcon.webp'],
    ];
@endphp
<div class="accueil-v2">
    <section class="av-hero" aria-labelledby="titre-accueil">
        <img class="av-hero-image" src="{{ $image }}" alt="" width="1536" height="1024" fetchpriority="high" decoding="async">
        <div class="av-hero-ombre"></div>
        <div class="cont av-hero-texte"><span class="surtitre">Votre séjour en Côte d’Ivoire</span><h1 id="titre-accueil">Un lieu à vous.<br>Le temps d’un séjour.</h1><p>{{ Marque::slogan() ?: 'Trouvez votre résidence meublée en Côte d’Ivoire, avec des annonces contrôlées et un prix clair avant de réserver.' }}</p><a class="lien-clair" href="{{ route('a-propos') }}">Découvrir {{ $nom }} <span aria-hidden="true">→</span></a></div>
    </section>
    <div class="cont av-recherche-cont">
        <form class="av-recherche" method="get" action="{{ route('recherche') }}" aria-label="Rechercher un séjour">
            <h2>Où souhaitez-vous séjourner ?</h2>
            <div class="av-recherche-champs">
                <label class="av-commune" for="accueil-commune"><span>Commune</span><select id="accueil-commune" name="commune"><option value="">Toutes les communes</option>@foreach($toutesCommunes as $c)<option>{{ $c }}</option>@endforeach</select></label>
                <label for="accueil-arrivee"><span>Arrivée</span><input id="accueil-arrivee" type="date" name="arrivee" min="{{ now()->toDateString() }}" required></label>
                <label for="accueil-depart"><span>Départ</span><input id="accueil-depart" type="date" name="depart" min="{{ now()->addDay()->toDateString() }}" required></label>
                <label class="av-voyageurs" for="accueil-personnes"><span>Voyageurs</span><select id="accueil-personnes" name="personnes">@foreach([1, 2, 3, 4, 5, 6, 8, 10, 12] as $n)<option value="{{ $n }}" @selected($n === 2)>{{ $n }} {{ $n === 1 ? 'personne' : 'personnes' }}</option>@endforeach</select></label>
                <button class="btn-pl" type="submit"><x-ui.icone nom="recherche" classe="ic" /><span>Rechercher un logement</span></button>
            </div>
            <p class="av-recherche-note">Choisissez vos dates pour consulter les logements disponibles et le montant de votre séjour.</p>
        </form>
    </div>
    <div class="cont av-reassurance">
        <div><x-ui.icone nom="bouclier" classe="ic" /><p><b>Identité du gérant contrôlée</b><span>Sa pièce d’identité est validée avant publication.</span></p></div>
        <div><x-ui.icone nom="prix" classe="ic" /><p><b>Total affiché avant paiement</b><span>Consultez le montant du séjour avant de réserver.</span></p></div>
        <div><x-ui.icone nom="carte" classe="ic" /><p><b>Mobile Money ou carte</b><span>Les moyens disponibles sont précisés au paiement.</span></p></div>
    </div>

    @if($unites->isNotEmpty())
    <section class="av-section" aria-labelledby="offres-titre"><div class="cont">
        <div class="av-section-titre"><div><span class="surtitre">Logements à la une</span><h2 id="offres-titre">Posez vos valises,<br class="av-desktop"> sentez-vous chez vous.</h2><p>Des offres publiées par nos gérants, à découvrir pour votre prochain séjour.</p></div><a href="{{ route('recherche') }}" class="lien-fl">Voir les logements <span aria-hidden="true">→</span></a></div>
        <div class="av-offres">
        @foreach($unites as $u)
            @php $r = $u->residence; $prix = $u->tarifs->firstWhere('type_periode', 'standard'); $photo = $u->photos->first() ?? $r->photos->first(); @endphp
            <article class="av-offre">
                <a class="av-offre-photo" href="{{ route('logement', $u) }}" aria-label="Découvrir {{ $u->nom }}"><x-ui.photo :photo="$photo" classe="h-full w-full" :plat="true" :alt="'Photo de '.$u->nom" />@if(in_array($u->residence_id, $enAvant, true))<span class="av-etiquette">Sponsorisé</span>@endif</a>
                <div class="av-offre-corps">@if($r->aBadgeGestion())<span class="pg-badge">✓ Gestion vérifiée</span>@endif<h3><a href="{{ route('logement', $u) }}">{{ $u->nom }}</a></h3><p>{{ $r->commune }}@if($r->quartier) · {{ $r->quartier }}@endif</p><p>{{ $u->capacite }} voyageur(s) · {{ ucfirst($u->type) }}</p><div class="av-offre-bas"><p><b>{{ number_format($prix->prix_nuit, 0, ',', "\u{202F}") }} FCFA</b><span> / nuit</span></p><a href="{{ route('logement', $u) }}" class="lien-fl">Voir le logement <span aria-hidden="true">→</span></a></div></div>
            </article>
        @endforeach
        </div>
    </div></section>
    @endif

    <section class="av-section av-exemples" aria-labelledby="exemples-titre"><div class="cont">
        <div class="av-section-titre"><div><span class="surtitre">Pour vous projeter</span><h2 id="exemples-titre">À chaque séjour, son cadre.</h2><p>Un studio pour une escapade, un appartement pour quelques jours : imaginez votre prochain pied-à-terre.</p></div></div>
        <div class="av-demo-notice" role="note"><x-ui.icone nom="photo" classe="ic" /><p><b>Exemples de présentation — offres fictives, non réservables.</b> Les visuels sont des illustrations et les tarifs sont donnés pour la démonstration. <a href="{{ route('recherche') }}">Consulter les vrais logements →</a></p></div>
        <div class="av-offres">
        @foreach($exemples as $e)
            <article class="av-offre av-demo" data-offre-fictive><div class="av-offre-photo"><img src="{{ asset('images/sejours/'.$e['photo']) }}" alt="Illustration d’un logement fictif : {{ $e['titre'] }}" width="768" height="512" loading="lazy" decoding="async"><span class="av-etiquette">Exemple fictif</span></div><div class="av-offre-corps"><h3>{{ $e['titre'] }}</h3><p>{{ $e['lieu'] }} · {{ $e['details'] }}</p><div class="av-offre-bas"><p><b>{{ number_format($e['prix'], 0, ',', "\u{202F}") }} FCFA</b><span> / nuit · tarif fictif</span></p></div><span class="av-non-reservable">Illustration uniquement · Non réservable</span></div></article>
        @endforeach
        </div>
    </div></section>

    @if($communes->isNotEmpty())
    <section class="av-section av-destinations" aria-labelledby="communes-titre"><div class="cont"><div class="av-section-titre"><div><span class="surtitre">Choisissez votre commune</span><h2 id="communes-titre">La Côte d’Ivoire, à votre rythme.</h2><p>Retrouvez les communes où nos gérants proposent des offres publiées.</p></div></div><div class="av-communes">@foreach($communes as $c)<a href="{{ route('recherche', ['commune' => $c->commune]) }}"><x-ui.icone nom="pin" classe="ic" /><span><b>{{ $c->commune }}</b><small>{{ $c->nombre }} logement{{ $c->nombre > 1 ? 's' : '' }} publié{{ $c->nombre > 1 ? 's' : '' }}</small></span><span aria-hidden="true">↗</span></a>@endforeach</div></div></section>
    @endif

    <section class="av-section" id="engagements" aria-labelledby="engagements-titre"><div class="cont av-confiance"><div class="av-confiance-visuel"><img src="{{ asset('images/sejours/salon-balcon.webp') }}" alt="Intérieur illustratif d’un séjour meublé chaleureux" width="768" height="700" loading="lazy" decoding="async"><p>Des contrôles concrets,<br>des informations claires.</p></div><div class="av-confiance-texte"><span class="surtitre">La confiance se construit</span><h2 id="engagements-titre">Sachez ce que<br>vous réservez.</h2>
        <ol><li><span>01</span><div><h3>Un gérant identifié</h3><p>Sa pièce d’identité est contrôlée avant la publication de ses résidences. L’annonce fait elle aussi l’objet d’une validation par Avelis.</p></div></li><li><span>02</span><div><h3>Des badges qui ont un sens</h3><p><b>Gérant certifié</b> : identité et certificat de résidence validés. <b>Gestion vérifiée</b> : justificatif du droit de gérer contrôlé pour cette résidence. Ces reconnaissances sont distinctes et facultatives.</p></div></li><li><span>03</span><div><h3>Vos conditions avant de payer</h3><p>Consultez le montant total et les conditions d’annulation avant confirmation. Vous choisissez en connaissant les règles de votre séjour.</p></div></li></ol><a href="{{ route('a-propos') }}" class="lien-fl">Comprendre nos engagements <span aria-hidden="true">→</span></a>
    </div></div></section>

    <section class="av-section av-comment" id="comment" aria-labelledby="comment-titre"><div class="cont"><span class="surtitre">Comment ça marche</span><h2 id="comment-titre">De la recherche à l’arrivée, simplement.</h2><div class="av-etapes"><div><x-ui.icone nom="recherche" classe="ic" /><div><h3>01 · Choisissez votre séjour</h3><p>Indiquez la commune, les dates et les voyageurs. Comparez les logements disponibles et le total du séjour.</p></div></div><div><x-ui.icone nom="carte" classe="ic" /><div><h3>02 · Réservez et payez</h3><p>Selon l’offre, réglez un acompte ou la totalité. Vos dates sont gardées {{ $blocage }} pendant le paiement.</p></div></div><div><x-ui.icone nom="cle" classe="ic" /><div><h3>03 · Préparez votre arrivée</h3><p>Après confirmation, retrouvez votre réservation et les coordonnées du gérant pour organiser votre accueil.</p></div></div></div></div></section>

    @if($avis->isNotEmpty())
    <section class="av-section"><div class="cont"><div class="av-section-titre"><div><span class="surtitre">Leurs séjours, leurs mots</span><h2>Ce que disent nos voyageurs</h2></div></div><div class="av-offres">@foreach($avis as $a)@php $mots = preg_split('/\s+/', trim($a->client->nom)); $court = $mots[0].(count($mots) > 1 ? ' '.mb_strtoupper(mb_substr(end($mots), 0, 1)).'.' : ''); @endphp<blockquote class="avis"><div class="etoiles" aria-label="{{ $a->note }} sur 5">{{ str_repeat('★', $a->note) }}</div><p>« {{ $a->commentaire }} »</p><footer><b>{{ $court }}</b> · séjour à {{ $a->reservation->unite->residence->commune }}</footer></blockquote>@endforeach</div></div></section>
    @endif

    <section class="av-section av-partenaires"><div class="cont"><div class="av-partenaire"><img src="{{ asset('images/sejours/appartement.webp') }}" alt="" width="768" height="512" loading="lazy" decoding="async"><div><span class="surtitre">Vous gérez une résidence meublée ?</span><h2>Votre résidence mérite<br>d’être découverte.</h2><p>Présentez vos logements, gérez vos disponibilités et recevez des réservations avec {{ $nom }}. Votre dossier gérant accompagne toutes vos résidences.</p><a href="{{ route('devenir-partenaire') }}" class="btn-or">Devenir gérant partenaire <span aria-hidden="true">→</span></a></div></div></div></section>

    <section class="av-section av-faq"><div class="cont av-faq-grille"><div><span class="surtitre">Nous vous accompagnons</span><h2>Une question<br>avant de réserver ?</h2><a href="{{ route('faq') }}" class="lien-fl">Consulter l’aide <span aria-hidden="true">→</span></a></div><div class="faq"><details><summary>Comment se déroule le paiement ?</summary><p>Les moyens de paiement disponibles et le montant à régler sont affichés avant confirmation. Selon l’offre, vous payez un acompte ou le total. Vos dates sont gardées {{ $blocage }} pendant cette étape.</p></details><details><summary>Que signifient les badges ?</summary><p>Le gérant certifié a fait valider son identité et son certificat de résidence. Le badge de gestion correspond à un justificatif contrôlé pour une résidence précise. Une annonce peut être publiée sans ces reconnaissances facultatives.</p></details><details><summary>Où consulter les conditions d’annulation ?</summary><p>Elles sont affichées avant le paiement et conservées avec votre réservation. Consultez les conditions de votre séjour avant de confirmer.</p></details></div></div></section>
</div>
