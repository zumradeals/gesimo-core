<div>
    <x-site.bandeau titre="Trouvez votre logement" sous="Seuls les logements réellement libres à vos dates sont affichés, avec le prix total du séjour." surtitre="Logements" />
    <section class="sect pt0"><div class="cont res-grille" style="padding-top:24px">
                <form wire:submit="chercher" class="filtres">
            <h2>Filtrer</h2>
            <div class="champ">
                <label class="etiquette" for="commune">Commune</label>
                <select id="commune" wire:model="commune" class="saisie">
                    <option value="">Toutes les communes</option>
                    @foreach($communes as $c) <option>{{ $c }}</option> @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="champ">
                    <label class="etiquette" for="arrivee">Arrivée</label>
                    <input id="arrivee" type="date" min="{{ now()->toDateString() }}" wire:model="arrivee" class="saisie">
                    @error('arrivee') <p class="erreur-champ">{{ $message }}</p> @enderror
                </div>
                <div class="champ">
                    <label class="etiquette" for="depart">Départ</label>
                    <input id="depart" type="date" min="{{ $arrivee ?: now()->toDateString() }}" wire:model="depart" class="saisie">
                    @error('depart') <p class="erreur-champ">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="champ">
                    <label class="etiquette" for="personnes">Personnes</label>
                    <input id="personnes" type="number" min="1" max="50" inputmode="numeric" wire:model="personnes" class="saisie">
                    @error('personnes') <p class="erreur-champ">{{ $message }}</p> @enderror
                </div>
                <div class="champ">
                    <label class="etiquette" for="budget">Budget max. du séjour</label>
                    <input id="budget" inputmode="numeric" wire:model="budget" class="saisie" placeholder="Ex. 70000">
                </div>
            </div>
            <div class="champ">
                <span class="etiquette">Équipements</span>
                <div class="sps">
                    @foreach($listeEquipements as $e)
                        @php $actif = in_array($e->id, array_map('intval', $equipements), true); @endphp
                        <button type="button" wire:click="basculerEquipement({{ $e->id }})" class="sp {{ $actif ? 'on' : '' }}" aria-pressed="{{ $actif ? 'true' : 'false' }}">{{ $e->libelle }}</button>
                    @endforeach
                </div>
            </div>
            @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif
            <button data-reseau class="btn-pl pleine" wire:loading.attr="disabled"><x-ui.icone nom="recherche" classe="ic" /> Chercher</button>
        </form>

        <section>
            @if($lancee && $nuits !== null)
                @if($resultats->isEmpty())
                    <x-ui.alerte type="info">Aucun logement libre à ces dates. Essayez d'autres dates ou une autre commune.</x-ui.alerte>
                @else
                    <div class="tri">
                        <span><b>{{ $resultats->count() }} logement{{ $resultats->count() > 1 ? 's' : '' }} libre{{ $resultats->count() > 1 ? 's' : '' }}</b> pour {{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }} · prix <b>totaux</b> du séjour</span>
                        <label class="flex items-center gap-2 text-[14px]">Trier par
                            <select wire:model.live="tri" class="saisie" style="min-height:40px;width:auto"><option value="recommande">Prix croissant</option><option value="prix_desc">Prix décroissant</option></select></label>
                    </div>
                    <div class="grille3" style="gap:16px">
                        @foreach($resultats as $ligne)
                            @php $u = $ligne['unite']; $r = $u->residence; @endphp
                            <a href="{{ route('logement', ['unite' => $u->id, 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes]) }}" class="lg {{ $ligne['sponsorise'] ? 'spons' : '' }}">
                                <div class="lg-photo">
                                    @if($ligne['sponsorise'])<span class="etq or">★ Sponsorisé</span>@endif
                                    <x-ui.photo :photo="$u->photos->first() ?? $r->photos->first()" classe="h-full" :plat="true" :alt="'Photo de '.$u->nom" />
                                </div>
                                <div class="lg-c">
                                    <div class="lg-t"><b>{{ $u->nom }}</b>@if($r->badge_verifie)<span class="etq vert">✔ Vérifié</span>@endif</div>
                                    <div class="lg-l"><x-ui.icone nom="pin" classe="ic pt" /> {{ $r->nom }} · {{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif · jusqu'à {{ $u->capacite }} pers.</div>
                                    <div class="lg-p"><b>{{ \App\Noyau\Services\Format::fcfa($ligne['total']) }}</b> <small>pour {{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }}</small></div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="carte-p" style="text-align:center;padding:48px 18px;justify-items:center">
                    <x-ui.icone nom="recherche" classe="ic gros" />
                    <p style="font-size:18px;font-weight:700;margin:0">Choisissez vos dates</p>
                    <p class="petit" style="margin:0">Nous vous montrons seulement les logements réellement libres, avec le prix total.</p>
                </div>
            @endif
        </section>
    </div></section>
</div>
