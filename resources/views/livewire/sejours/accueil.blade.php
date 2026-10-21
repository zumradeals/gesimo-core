@php
    use App\Parametres\Services\Marque;
    $fcfa = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $image = Marque::imageAccueilUrl();
    $nom = Marque::nom();
@endphp
<div>
    {{-- Héros --}}
    <section class="hero" @if($image) style="background-image: linear-gradient(135deg, rgba(11,90,84,.92), rgba(15,118,110,.82) 55%, rgba(20,149,138,.7)), url('{{ $image }}'); background-size: cover; background-position: center;" @endif>
        <div class="cont hero-in">
            <div class="hero-txt">
                <span class="surtitre clair">Résidences meublées · Côte d'Ivoire</span>
                <h1>Trouvez votre résidence meublée, réservez en toute confiance</h1>
                <p>{{ Marque::slogan() ?: 'Des logements vérifiés en Côte d\'Ivoire, avec le prix total affiché dès le départ et un paiement sécurisé.' }}</p>
                <ul class="hero-points">
                    <li><x-ui.icone nom="bouclier" classe="ic" /> Résidences vérifiées avant publication</li>
                    <li><x-ui.icone nom="prix" classe="ic" /> Prix total affiché, sans frais cachés</li>
                    <li><x-ui.icone nom="retour" classe="ic" /> Annulation simple jusqu'à {{ $delai }} h avant l'arrivée</li>
                </ul>
            </div>
            <form class="recherche" method="get" action="{{ route('recherche') }}">
                <h2>Où et quand voulez-vous séjourner ?</h2>
                <div class="r-grille">
                    <label>Commune
                        <select name="commune"><option value="">Toutes les communes</option>@foreach($toutesCommunes as $c)<option>{{ $c }}</option>@endforeach</select>
                    </label>
                    <label>Arrivée<input type="date" name="arrivee" min="{{ now()->toDateString() }}" required></label>
                    <label>Départ<input type="date" name="depart" min="{{ now()->addDay()->toDateString() }}" required></label>
                    <label>Personnes<input type="number" name="personnes" value="2" min="1" max="50" inputmode="numeric"></label>
                </div>
                <button class="btn-pl grand pleine"><x-ui.icone nom="recherche" classe="ic" /> Rechercher un logement</button>
                <p class="petit" style="margin-top:8px">Seuls les logements réellement libres à vos dates sont affichés.</p>
            </form>
        </div>
    </section>

    {{-- Bandeau de confiance --}}
    <section class="bandeau-confiance">
        <div class="cont bc-in">
            <div><x-ui.icone nom="bouclier" classe="ic" /><b>Gérants vérifiés</b><span>Pièces et photos contrôlées</span></div>
            <div><x-ui.icone nom="prix" classe="ic" /><b>Prix clair</b><span>Total affiché avant de payer</span></div>
            <div><x-ui.icone nom="carte" classe="ic" /><b>Paiement sécurisé</b><span>Mobile Money ou carte</span></div>
            <div><x-ui.icone nom="retour" classe="ic" /><b>Annulation simple</b><span>Remboursement jusqu'à {{ $delai }} h avant</span></div>
        </div>
    </section>

    {{-- Comment ça marche --}}
    <section class="sect" id="comment">
        <div class="cont">
            <div class="sect-t"><span class="surtitre">Simple et rapide</span><h2>Comment ça marche ?</h2><p>Réservez en quelques minutes, depuis votre téléphone.</p></div>
            <div class="etapes">
                <div class="etape"><span class="num">1</span><x-ui.icone nom="recherche" classe="ic gros" /><h3>Cherchez</h3><p>Choisissez la commune, vos dates et le nombre de personnes. Nous affichons les logements libres avec le prix total.</p></div>
                <div class="etape"><span class="num">2</span><x-ui.icone nom="carte" classe="ic gros" /><h3>Réservez et payez</h3><p>Vos dates sont gardées {{ $blocage }}. Payez l'acompte ou le total par Wave, Orange Money, MTN Money ou carte.</p></div>
                <div class="etape"><span class="num">3</span><x-ui.icone nom="maison" classe="ic gros" /><h3>Profitez de votre séjour</h3><p>Vous recevez la confirmation par e-mail avec les coordonnées du gérant. Il vous accueille à l'arrivée.</p></div>
            </div>
        </div>
    </section>

    {{-- Logements à la une (seulement s'il y en a) --}}
    @if($unites->isNotEmpty())
        <section class="sect alt">
            <div class="cont">
                <div class="sect-t gauche"><div><span class="surtitre">Sélection</span><h2>Logements à la une</h2><p>Des résidences vérifiées, prêtes à vous accueillir.</p></div>
                    <a href="{{ route('recherche') }}" class="lien-fl">Voir tous les logements <x-ui.icone nom="fleche" classe="ic" /></a></div>
                <div class="grille3">
                    @foreach($unites as $u)
                        @php $r = $u->residence; $sponso = in_array($u->residence_id, $enAvant, true); $prix = (int) $u->tarifs->firstWhere('type_periode', 'standard')->prix_nuit; @endphp
                        <a href="{{ route('logement', ['unite' => $u->id]) }}" class="lg {{ $sponso ? 'spons' : '' }}">
                            <div class="lg-photo">
                                @if($sponso)<span class="etq or">★ Sponsorisé</span>@endif
                                <x-ui.photo :photo="$u->photos->first() ?? $r->photos->first()" classe="h-full" :plat="true" :alt="'Photo de '.$u->nom" />
                            </div>
                            <div class="lg-c">
                                <div class="lg-t"><b>{{ $u->nom }}</b>@if($r->badge_verifie)<span class="etq vert">✔ Vérifié</span>@endif</div>
                                <div class="lg-l"><x-ui.icone nom="pin" classe="ic pt" /> {{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif · jusqu'à {{ $u->capacite }} pers.</div>
                                <div class="lg-p"><b>{{ number_format($prix, 0, ',', "\u{202F}") }}</b> <small>FCFA / nuit</small></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Communes (seulement s'il y en a) --}}
    @if($communes->isNotEmpty())
        <section class="sect">
            <div class="cont">
                <div class="sect-t"><span class="surtitre">Explorer</span><h2>Choisissez votre commune</h2></div>
                <div class="communes">
                    @foreach($communes as $c)
                        <a href="{{ route('recherche', ['commune' => $c->commune]) }}" class="commune"><span>{{ $c->commune }}</span><small>{{ $c->nombre }} logement{{ $c->nombre > 1 ? 's' : '' }}</small></a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Pourquoi --}}
    <section class="sect alt">
        <div class="cont">
            <div class="sect-t"><span class="surtitre">Pourquoi {{ $nom }}</span><h2>Une réservation en laquelle vous pouvez avoir confiance</h2></div>
            <div class="grille4">
                <div class="av"><x-ui.icone nom="bouclier" classe="ic gros" /><h3>Résidences vérifiées</h3><p>Chaque résidence est contrôlée (pièces, photos, droit de gérer) avant d'être publiée. Le badge « Vérifié » le garantit.</p></div>
                <div class="av"><x-ui.icone nom="prix" classe="ic gros" /><h3>Prix total affiché</h3><p>Le montant du séjour est le même partout : dans la recherche, sur la fiche et au paiement.</p></div>
                <div class="av"><x-ui.icone nom="carte" classe="ic gros" /><h3>Paiement protégé</h3><p>Le gérant est payé après votre arrivée. Vos paiements passent par un prestataire sécurisé.</p></div>
                <div class="av"><x-ui.icone nom="retour" classe="ic gros" /><h3>Annulation claire</h3><p>Remboursement intégral jusqu'à {{ $delai }} h avant l'arrivée. Les règles sont affichées avant de payer.</p></div>
            </div>
        </div>
    </section>

    {{-- Avis réels (masqué tant qu'il n'y en a pas) --}}
    @if($avis->isNotEmpty())
        <section class="sect">
            <div class="cont">
                <div class="sect-t"><span class="surtitre">Avis</span><h2>Ce que disent nos voyageurs</h2></div>
                <div class="grille3">
                    @foreach($avis as $a)
                        @php $mots = preg_split('/\s+/', trim($a->client->nom)); $court = $mots[0].(count($mots) > 1 ? ' '.mb_strtoupper(mb_substr(end($mots), 0, 1)).'.' : ''); @endphp
                        <blockquote class="avis">
                            <div class="etoiles" role="img" aria-label="{{ $a->note }} sur 5">{{ str_repeat('★', $a->note) }}{{ str_repeat('☆', 5 - $a->note) }}</div>
                            <p>« {{ $a->commentaire }} »</p>
                            <footer><b>{{ $court }}</b> · séjour à {{ $a->reservation->unite->residence->commune }}</footer>
                        </blockquote>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Invitation aux gérants --}}
    <section class="cta-par">
        <div class="cont cta-in">
            <div><span class="surtitre clair">Vous gérez une résidence meublée ?</span><h2>Recevez plus de réservations avec {{ $nom }}</h2><p>Visibilité, paiements sécurisés, calendrier simple, reversement après l'arrivée du client. Inscription gratuite.</p></div>
            <div class="cta-b"><a href="{{ route('devenir-partenaire') }}" class="btn-or grand">Devenir gérant partenaire</a></div>
        </div>
    </section>

    {{-- Questions fréquentes --}}
    <section class="sect">
        <div class="cont">
            <div class="sect-t"><span class="surtitre">Aide</span><h2>Questions fréquentes</h2></div>
            <div class="faq" style="max-width:820px;margin:0 auto">
                <details><summary>Comment payer ma réservation ?</summary><p>Par Wave, Orange Money, MTN Money ou carte bancaire, sur la page sécurisée de notre partenaire de paiement.</p></details>
                <details><summary>Mes dates sont-elles gardées pendant que je paie ?</summary><p>Oui, pendant {{ $blocage }}. Passé ce délai sans paiement, elles sont libérées automatiquement.</p></details>
                <details><summary>Puis-je annuler ma réservation ?</summary><p>Oui. Remboursement intégral jusqu'à {{ $delai }} heures avant l'arrivée ; ensuite, une nuit est retenue.</p></details>
            </div>
            <p class="centre" style="margin-top:14px"><a href="{{ route('faq') }}" class="lien-fl">Toutes les questions <x-ui.icone nom="fleche" classe="ic" /></a></p>
        </div>
    </section>
</div>
