<div class="grid gap-5">
    <section class="rounded-3xl bg-gradient-to-br from-marque-600 via-marque-500 to-amber-300/80 p-5 text-white lg:p-8">
        <h1 class="text-2xl font-extrabold lg:text-4xl">Trouvez votre résidence meublée en Côte d'Ivoire</h1>
        <p class="mt-1 text-white/90 lg:text-lg">Réservez en moins de 2 minutes. Prix total affiché, sans frais cachés.</p>
    </section>

    <div class="grid gap-5 lg:grid-cols-[380px_1fr] lg:items-start">
        <form wire:submit="chercher" class="carte grid gap-3 lg:sticky lg:top-24">
            <h2 class="text-lg font-extrabold">Trouver un séjour</h2>
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
                <div class="flex flex-wrap gap-2">
                    @foreach($listeEquipements as $e)
                        @php $actif = in_array($e->id, array_map('intval', $equipements), true); @endphp
                        <button type="button" wire:click="basculerEquipement({{ $e->id }})" class="puce {{ $actif ? 'puce-active' : '' }}" aria-pressed="{{ $actif ? 'true' : 'false' }}">{{ $e->libelle }}</button>
                    @endforeach
                </div>
            </div>
            @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif
            <button data-reseau class="btn btn-bloc" wire:loading.attr="disabled"><x-ui.icone nom="recherche" classe="h-5 w-5" /> Chercher</button>
        </form>

        <section class="grid content-start gap-4">
            @if($lancee && $nuits !== null)
                @if($resultats->isEmpty())
                    <x-ui.alerte type="info">Aucun logement libre à ces dates. Essayez d'autres dates ou une autre commune.</x-ui.alerte>
                @else
                    <p class="text-gris-doux"><b class="text-encre">{{ $resultats->count() }} logement{{ $resultats->count() > 1 ? 's' : '' }} libre{{ $resultats->count() > 1 ? 's' : '' }}</b> pour {{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }}. Les prix affichés sont les prix <b>totaux</b> du séjour.</p>
                    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach($resultats as $ligne)
                            @php $u = $ligne['unite']; $r = $u->residence; @endphp
                            <article class="carte grid content-start gap-2 {{ $ligne['sponsorise'] ? '!border-2 !border-amber-400' : '' }}">
                                @if($ligne['sponsorise'])<span class="w-fit rounded-full bg-amber-100 px-3 py-0.5 text-[13px] font-bold text-amber-800">⭐ Sponsorisé</span>@endif
                                <x-ui.photo :photo="$u->photos->first() ?? $r->photos->first()" classe="h-48" />
                                <h3 class="text-lg font-extrabold">{{ $u->nom }}
                                    @if($r->badge_verifie) <span class="ml-1 inline-block rounded-full bg-green-100 px-2 py-0.5 align-middle text-[12px] font-bold text-green-800">✔ Vérifié GESIMO</span> @endif
                                </h3>
                                <p class="aide">{{ $r->nom }} · {{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif · jusqu'à {{ $u->capacite }} pers.</p>
                                <p class="text-2xl font-extrabold">{{ \App\Noyau\Services\Format::fcfa($ligne['total']) }} <span class="text-sm font-normal text-gris-doux">pour {{ $nuits }} nuit{{ $nuits > 1 ? 's' : '' }}</span></p>
                                <a href="{{ route('logement', ['unite' => $u->id, 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes]) }}" class="btn btn-bloc">Voir ce logement</a>
                            </article>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="carte grid place-items-center gap-2 py-12 text-center text-gris-doux">
                    <x-ui.icone nom="recherche" classe="h-10 w-10 text-marque-500" />
                    <p class="text-lg font-semibold text-encre">Choisissez vos dates</p>
                    <p>Nous vous montrons seulement les logements réellement libres, avec le prix total.</p>
                </div>
            @endif
        </section>
    </div>
</div>
