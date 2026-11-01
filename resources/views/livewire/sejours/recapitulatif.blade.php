@php
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $r = $u->residence;
    $duree = $dureeMinutes % 60 === 0 ? ($dureeMinutes / 60).' heure'.($dureeMinutes > 60 ? 's' : '') : $dureeMinutes.' minutes';
    $lienFiche = route('logement', ['unite' => $u->id, 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes]);
    $pret = $valide && ! $horsLimites && $total !== null && $libre;
@endphp
<div class="rv-page">
    <p class="fil"><a href="{{ $lienFiche }}">← Modifier mon séjour</a></p>
    <x-site.etapes-reservation :etape="1" />
    <h1 class="rv-titre">Quelques informations avant de payer</h1>

    @if(! $valide)
        <x-ui.alerte type="erreur">Les dates ne sont pas valides. <a class="font-bold underline" href="{{ route('logement', ['unite' => $u->id]) }}">Revenez à la fiche du logement</a> pour les choisir.</x-ui.alerte>
    @elseif($horsLimites)
        <x-ui.alerte type="attention">
            @if($nuits > $nuitsMax) Au-delà de {{ $nuitsMax }} nuits, la réservation en ligne n'est pas possible. <a class="font-bold underline" href="{{ route('contact') }}">Contactez-nous</a> pour organiser un long séjour.
            @else Le séjour minimum est de {{ $nuitsMin }} nuit{{ $nuitsMin > 1 ? 's' : '' }}. @endif
            <a class="font-bold underline" href="{{ $lienFiche }}">Modifier mon séjour</a>
        </x-ui.alerte>
    @elseif($total === null)
        <x-ui.alerte type="attention">Ce logement n'a pas encore de prix.</x-ui.alerte>
    @elseif(! $libre)
        <x-ui.alerte type="erreur"><b>Ces dates viennent d'être prises.</b> Un autre voyageur a réservé avant vous.@if($suggestions !== []) Voici les dates libres les plus proches pour {{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }} :@endif</x-ui.alerte>
        @if($suggestions !== [])
            <div class="rv-suggestions">
                @foreach($suggestions as $sug)
                    <a href="{{ route('reserver', ['unite' => $u->id, 'arrivee' => $sug['arrivee']->toDateString(), 'depart' => $sug['depart']->toDateString(), 'personnes' => $personnes]) }}">Du {{ $sug['arrivee']->translatedFormat('j M') }} au {{ $sug['depart']->translatedFormat('j M Y') }}<span class="petit">Choisir</span></a>
                @endforeach
            </div>
        @endif
        <a class="btn-sec" href="{{ route('recherche', ['commune' => $r->commune]) }}">Voir d'autres logements à {{ $r->commune }}</a>
    @else
        <div class="rv-cols">
            <form wire:submit="garder" class="rv-formulaire" novalidate>
                <section class="rv-carte">
                    <h2>Qui voyage ?</h2>
                    <div class="champ"><label class="etiquette" for="nom">Nom complet</label><span class="petit">Écrivez-le comme sur votre pièce d'identité.</span>
                        <input id="nom" wire:model="nom" class="saisie" autocomplete="name">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ"><label class="etiquette" for="telephone">Téléphone</label><span class="petit">Le gérant vous appelle seulement en cas de besoin.</span>
                        <input id="telephone" type="tel" inputmode="tel" wire:model="telephone" class="saisie" autocomplete="tel">@error('telephone')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                </section>

                <section class="rv-carte">
                    <h2 id="motif-titre">Pourquoi venez-vous ?</h2>
                    <p class="petit" style="margin:0">Choisissez une réponse. Seul le gérant la voit, elle ne change jamais le prix.</p>
                    <div class="rv-radios" role="radiogroup" aria-labelledby="motif-titre">
                        @foreach($motifs as $code => $libelle)
                            <label><input type="radio" name="motif" value="{{ $code }}" wire:model.live="motif"> {{ $libelle }}</label>
                        @endforeach
                    </div>
                    @error('motif')<p class="erreur-champ">{{ $message }}</p>@enderror
                    @if($motif === 'autre')
                        <div class="champ"><label class="etiquette" for="precision">Précisez en quelques mots</label>
                            <input id="precision" wire:model="motifPrecision" class="saisie" maxlength="120" placeholder="Ex. : entretien d'embauche">@error('motifPrecision')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    @endif
                    @if($motif === 'evenement')
                        <x-ui.alerte type="info"><b>Événement :</b> certaines résidences ont des règles pour les fêtes et le bruit. Lisez le règlement de la résidence avant de continuer.@if($r->reglement) <br><span class="petit">« {{ $r->reglement }} »</span>@endif</x-ui.alerte>
                    @endif
                </section>

                <section class="rv-carte">
                    <h2>Votre arrivée <span class="petit" style="font-weight:400">(facultatif)</span></h2>
                    <div class="champ"><label class="etiquette" for="heure">Heure d'arrivée prévue</label>
                        <select id="heure" wire:model="heureArrivee" class="saisie">@foreach($heures as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach</select></div>
                    <div class="champ"><label class="etiquette" for="message">Un mot pour le gérant</label>
                        <textarea id="message" wire:model="message" class="saisie" rows="3" maxlength="500" placeholder="Ex. : j'arrive en avion à 20 h, pouvez-vous m'attendre ?"></textarea>@error('message')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                </section>

                <section class="rv-carte rv-ivoire">
                    <h2>Votre pièce d'identité</h2>
                    @if($pieceDejaFournie)
                        <p style="margin:0"><b>✓ Votre pièce d'identité est déjà enregistrée</b> dans votre profil : vous n'avez rien à envoyer.</p>
                    @else
                        <p style="margin:0">Vous n'avez rien à envoyer maintenant. Après le paiement, on vous proposera de l'ajouter en 2 minutes, <b>une seule fois pour toutes vos réservations</b>. Elle doit être fournie avant votre arrivée.</p>
                    @endif
                </section>

                <section class="rv-carte">
                    <label class="rv-case"><input type="checkbox" wire:model.live="accord"><span>J'ai lu le règlement de la résidence et j'accepte les <a href="{{ route('conditions') }}" target="_blank" rel="noopener">conditions générales</a> d'Avelis Séjours.</span></label>
                    @error('accord')<p class="erreur-champ">{{ $message }}</p>@enderror
                </section>

                <div class="rv-action rv-action-formulaire">
                    @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif
                    <button type="submit" wire:loading.attr="disabled" class="btn-pl grand pleine" @disabled(! $accord)>Continuer vers le paiement</button>
                    @if(! $accord)<p class="petit" style="margin:0">Cochez la case d'accord pour continuer.</p>@endif
                </div>
            </form>

            <aside class="rv-resume">
                <section class="rv-carte">
                    <div class="rv-mini"><div class="rv-mini-photo"><x-ui.photo :photo="$u->photos->first() ?? $r->photos->first()" classe="h-20" :alt="'Photo de '.$u->nom" /></div>
                        <div><b>{{ $u->nom }}</b><span class="petit">{{ $r->nom }}, {{ $r->commune }}</span></div></div>
                    <div class="ligne"><span>Arrivée</span><b>{{ \Carbon\CarbonImmutable::parse($arrivee)->translatedFormat('D j M Y') }}</b></div>
                    <div class="ligne"><span>Départ</span><b>{{ \Carbon\CarbonImmutable::parse($depart)->translatedFormat('D j M Y') }} <small class="petit">avant {{ $heureDepart }}</small></b></div>
                    <div class="ligne"><span>Durée</span><b>{{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }} · {{ $personnes }} personne{{ $personnes > 1 ? 's' : '' }}</b></div>
                    <div class="ligne total"><span>Total du séjour</span><b>{{ $fr($total) }}</b></div>
                    <p class="petit" style="margin:0">Aucun autre frais.
                        @if($limiteAnnulation && $limiteAnnulation->gt(now()))<b>Annulation gratuite jusqu'au {{ $limiteAnnulation->translatedFormat('j M') }} à {{ \App\Sejours\Services\ReglesSejour::heureLisible($limiteAnnulation->format('H:i')) }}.</b>@endif</p>
                    @if($solde > 0)
                        <div class="ligne"><span>À payer en ligne (acompte)</span><b>{{ $fr($acompte) }}</b></div>
                        <div class="ligne"><span>À régler au gérant à l'arrivée</span><b>{{ $fr($solde) }}</b></div>
                    @endif
                    <div class="rv-minuteur"><x-ui.icone nom="horloge" classe="ic" /><span>En continuant, vos dates sont <b>gardées {{ $duree }}</b> pour vous. Sans paiement dans ce délai, elles sont libérées.</span></div>
                </section>
            </aside>
        </div>
    @endif
</div>
