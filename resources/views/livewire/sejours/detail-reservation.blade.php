@php
    use App\Sejours\Enums\HeureArriveePrevue;
    use App\Sejours\Enums\MotifSejour;
    use App\Sejours\Enums\StatutReservation as S;
    use App\Sejours\Services\ReglesSejour;
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $u = $r->unite; $res = $u->residence; $gerant = $res->gerant;
    $contactVisible = in_array($r->statut, [S::Confirmee, S::ArriveeEnregistree, S::Terminee, S::Litige], true);
    $jours = (int) now()->startOfDay()->diffInDays($r->date_arrivee->startOfDay(), false);
    $arrivee = in_array($r->statut, [S::Confirmee, S::EnAttente], true) && $jours >= 0
        ? ($jours === 0 ? 'Arrivée aujourd\'hui' : ($jours === 1 ? 'Arrivée demain' : "Arrivée dans {$jours} jours")) : null;
    $motif = $r->motif_sejour ? MotifSejour::tryFrom($r->motif_sejour) : null;
    $heurePrevue = $r->heure_arrivee_prevue && $r->heure_arrivee_prevue !== 'inconnue' ? HeureArriveePrevue::tryFrom($r->heure_arrivee_prevue) : null;
    $etapes = [
        ['Réservation créée · '.$r->created_at->translatedFormat('j M'), true],
        ['Paiement reçu', $paye > 0],
        ['Confirmée', in_array($r->statut, [S::Confirmee, S::ArriveeEnregistree, S::Terminee, S::Litige], true)],
        ['Arrivée · '.$r->date_arrivee->translatedFormat('j M'), in_array($r->statut, [S::ArriveeEnregistree, S::Terminee], true)],
        ['Séjour terminé', $r->statut === S::Terminee],
    ];
    $heureArriveeStd = ReglesSejour::heureLisible($r->annulation_heure_arrivee ?? ReglesSejour::heureArrivee());
    $flexible = $res->politique_annulation === 'flexible';
@endphp
<div class="mr-page">
    <p class="fil"><a href="{{ route('mes-reservations') }}">← Mes réservations</a> › Réservation n° {{ $r->id }}</p>
    <div class="mr-entete"><div>
        <div class="mr-titre"><h1>{{ $u->nom }}</h1>@if($r->statut === S::EnAttente)<span class="mr-badge att">En attente de paiement</span>@else<x-ui.badge-statut :statut="$r->statut" />@endif</div>
        <p class="petit">{{ $res->nom }} · {{ $res->commune }}@if($res->quartier), {{ $res->quartier }}@endif @if($arrivee) · {{ $arrivee }}@endif</p>
    </div></div>
    @if($info)<x-ui.alerte type="succes">{{ $info }}</x-ui.alerte>@endif
    @if($litigeOuvert)<x-ui.alerte type="attention">Vous avez signalé un problème : l'équipe Avelis l'étudie et vous répond sous 48 h.</x-ui.alerte>@endif

    <div class="mr-cols">
        <div class="mr-col">
            @if($r->statut === S::EnAttente)
                <section class="mr-etape"><span class="mr-surtitre">Dates gardées pour vous</span>
                    <h2>Plus que {{ $r->expire_le->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }} pour payer</h2>
                    <p>Sans paiement dans ce délai, les dates sont libérées et rien n'est débité.</p>
                    <a href="{{ route('payer', $r) }}" data-reseau class="btn-pl grand">Payer {{ $fr($r->acompte) }}</a></section>
            @elseif($r->statut === S::Confirmee)
                <section class="mr-etape" id="avant-arrivee"><span class="mr-surtitre">Avant votre arrivée</span><h2>À vérifier</h2>
                    <ul class="mr-faire">
                        <li class="fait"><i>✓</i><div><b>Réservation confirmée et payée</b><span class="petit">{{ $fr($paye) }} payés en ligne</span></div></li>
                        <li class="{{ $pieceFournie ? 'fait' : 'afaire' }}"><i>{{ $pieceFournie ? '✓' : '!' }}</i><div><b>{{ $pieceFournie ? 'Pièce d\'identité fournie' : 'Ajoutez votre pièce d\'identité' }}</b>
                            <span class="petit">{{ $pieceFournie ? 'Présentez la même pièce, en original, le jour de l\'arrivée.' : 'Le gérant en a besoin pour vous accueillir. Une seule fois pour toutes vos réservations.' }}</span></div></li>
                        <li class="fait"><i>✓</i><div><b>Contact du gérant</b><span class="petit">{{ $gerant->nom }} · {{ \App\Noyau\Services\Telephone::afficher($gerant->telephone) }}</span></div></li>
                    </ul></section>
                @unless($pieceFournie)
                    <section class="mr-carte" id="piece-identite"><h2>Ma pièce d'identité</h2>@livewire(\App\Sejours\Livewire\PieceIdentite::class, ['source' => 'confirmation'])</section>
                @endunless
            @endif

            <section class="mr-carte"><h2>Votre séjour</h2>
                <div class="mr-dates">
                    <div><span class="petit">Arrivée</span><b>{{ $r->date_arrivee->translatedFormat('D j M Y') }}</b><span class="petit">à partir de {{ $heureArriveeStd }}</span></div>
                    <div><span class="petit">Départ</span><b>{{ $r->date_depart->translatedFormat('D j M Y') }}</b>@if($r->heure_depart)<span class="petit">avant {{ ReglesSejour::heureLisible($r->heure_depart) }}</span>@endif</div>
                </div>
                <div class="ligne"><span>Durée</span><b>{{ $r->date_arrivee->diffInDays($r->date_depart) }} nuit(s) · {{ $r->nombre_personnes }} personne(s)</b></div>
                @if($r->nom_client)<div class="ligne"><span>Voyageur</span><b>{{ $r->nom_client }}</b></div>@endif
                @if($motif)<div class="ligne"><span>Motif</span><b>{{ $motif === MotifSejour::Autre && $r->motif_precision ? 'Autre : '.$r->motif_precision : $motif->libelle() }}</b></div>@endif
                @if(! $edition)
                    @if($heurePrevue)<div class="ligne"><span>Heure d'arrivée prévue</span><b>{{ $heurePrevue->libelle() }}</b></div>@endif
                    @if($r->message_gerant)<div class="ligne"><span>Votre message au gérant</span><b>« {{ $r->message_gerant }} »</b></div>@endif
                    @if($modifiable)
                        <button type="button" wire:click="$set('edition', true)" class="mr-lien">✎ Modifier l'heure d'arrivée ou mon message</button>
                        <p class="petit" style="margin:0">Vous pouvez les modifier jusqu'à la veille de votre arrivée.</p>
                    @endif
                @else
                    <form wire:submit="enregistrerInformations" class="mr-form" novalidate>
                        @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
                        <div class="champ"><label class="etiquette" for="heure">Heure d'arrivée prévue</label>
                            <select id="heure" wire:model="heureArrivee" class="saisie">@foreach($heures as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach</select></div>
                        <div class="champ"><label class="etiquette" for="message">Un mot pour le gérant</label>
                            <textarea id="message" wire:model="message" class="saisie" rows="3" maxlength="500"></textarea></div>
                        <div class="mr-deux"><button type="submit" wire:loading.attr="disabled" class="btn-pl">Enregistrer</button><button type="button" wire:click="$set('edition', false)" class="btn-sec">Annuler</button></div>
                    </form>
                @endif
            </section>

            @if($contactVisible)
                <section class="mr-carte"><h2>Le gérant et le lieu</h2>
                    <div class="ligne"><span>Gérant</span><b>{{ $gerant->nom }}</b></div>
                    <div class="ligne"><span>Téléphone</span><b>{{ \App\Noyau\Services\Telephone::afficher($gerant->telephone) }}</b></div>
                    @if($res->adresse)<div class="ligne"><span>Adresse</span><b style="text-align:right">{{ $res->adresse }}<br><span class="petit" style="font-weight:400">{{ $res->quartier ? $res->quartier.', ' : '' }}{{ $res->commune }}</span></b></div>
                    @else<p class="petit" style="margin:0">{{ $res->commune }}@if($res->quartier), {{ $res->quartier }}@endif. Le gérant vous précisera l'adresse exacte si besoin.</p>@endif
                    <a class="btn-pl" href="{{ route('reservation.venir', $r) }}">📍 Comment venir</a>
                    <div class="mr-deux">
                        <a class="btn-sec" href="tel:{{ $gerant->telephone }}">📞 Appeler</a>
                        @if($res->latitude !== null)<a class="btn-sec" href="https://www.google.com/maps?q={{ $res->latitude }},{{ $res->longitude }}" target="_blank" rel="noopener">📍 Voir sur la carte</a>@endif
                    </div>
                    @if($res->reglement)<p class="petit" style="margin:0">Règlement de la résidence : « {{ $res->reglement }} »</p>@endif
                </section>
            @else
                <section class="mr-carte"><h2>Le gérant</h2><p class="petit" style="margin:0">Les coordonnées du gérant et l'adresse du logement vous sont données dès que votre réservation est confirmée.</p></section>
            @endif

            <section class="mr-carte"><h2>Suivi</h2>
                <ol class="mr-suivi">@foreach($etapes as [$libelle, $fait])<li class="{{ $fait ? 'fait' : '' }}">{{ $libelle }}</li>@endforeach</ol>
            </section>
        </div>

        <aside class="mr-col mr-colle">
            <section class="mr-carte"><h2>Paiement</h2>
                <div class="ligne"><span>Total du séjour</span><b>{{ $fr($r->montant_total) }}</b></div>
                @if($r->statut === S::EnAttente)
                    @if($r->solde > 0)<div class="ligne"><span>À payer en ligne (acompte)</span><b>{{ $fr($r->acompte) }}</b></div><div class="ligne"><span>À régler au gérant à l'arrivée</span><b>{{ $fr($r->solde) }}</b></div>@endif
                    <a href="{{ route('payer', $r) }}" data-reseau class="btn-pl grand pleine">Payer {{ $fr($r->acompte) }}</a>
                @else
                    <div class="ligne"><span>Payé en ligne</span><b>{{ $fr($paye) }}</b></div>
                    @if($r->solde > 0 && $contactVisible)<div class="ligne total"><span>Reste à payer au gérant</span><span>{{ $fr($r->solde) }}</span></div>@endif
                @endif
                @if($paye > 0)<a href="{{ route('reservation.recu', $r) }}" data-reseau target="_blank" class="btn-sec pleine">Télécharger le reçu</a>@endif
            </section>

            @if($apercu)
                <section class="mr-carte"><h2>Annulation</h2>
                    @if($flexible)
                        @if($apercu['limite']->isFuture())<p style="margin:0">Annulation gratuite jusqu'au <b>{{ $apercu['limite']->translatedFormat('j M') }} à {{ ReglesSejour::heureLisible($apercu['limite']->format('H:i')) }}</b>.</p>
                        @else<p style="margin:0">Le délai d'annulation gratuite est dépassé pour cette réservation.</p>@endif
                    @else
                        <p style="margin:0">Conditions d'annulation plus strictes, choisies par le gérant.</p>
                    @endif
                    <p class="petit" style="margin:0">Si vous annulez maintenant, vous êtes remboursé de <b>{{ $fr($apercu['rembourse']) }}</b>@if($apercu['retenu'] > 0) ({{ $fr($apercu['retenu']) }} retenus, soit une nuit)@endif.</p>
                    @if($jours >= 0)<a href="{{ route('reservation.annuler', $r) }}" data-reseau class="btn-sec pleine rouge-b2">Annuler ma réservation</a>@endif
                </section>
            @elseif($r->statut === S::EnAttente)
                <a href="{{ route('mes-reservations') }}" class="mr-lien">Annuler cette réservation depuis la liste</a>
            @endif

            @if($r->statut === S::Terminee)
                <section class="mr-carte">
                    @if($avis)<x-ui.alerte type="succes">Merci pour votre avis : <x-ui.etoiles :note="$avis->note" /></x-ui.alerte>
                    @else<a href="{{ route('reservation.avis', $r) }}" data-reseau class="btn-pl pleine">Donner mon avis</a>@endif
                    <a href="{{ route('logement', $u) }}" class="btn-sec pleine">Réserver à nouveau</a>
                </section>
            @elseif(in_array($r->statut, [S::Confirmee, S::ArriveeEnregistree], true))
                <p class="petit" style="margin:0">Après le séjour, vous pourrez donner votre avis ici.</p>
            @endif
            @if(! $litigeOuvert && in_array($r->statut, [S::Confirmee, S::ArriveeEnregistree, S::Terminee, S::Annulee, S::Litige], true) && $r->origine !== 'hors_plateforme')
                <a href="{{ route('reservation.probleme', $r) }}" data-reseau class="mr-lien rouge">⚠ Signaler un problème</a>
            @endif
        </aside>
    </div>
</div>
