@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="grid gap-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="adm-top"><div><h1>{{ $residence ? $residence->nom : 'Ma résidence' }}</h1></div></div>
        @if($residence) @include('livewire.sejours.partiels.statut-residence', ['r' => $residence]) @endif
    </div>

    @if(session('message')) <x-ui.alerte type="succes">{{ session('message') }}</x-ui.alerte> @endif
    @if($message) <x-ui.alerte type="succes">{{ $message }}</x-ui.alerte> @endif
    @if($erreur) <x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte> @endif
    @if($residence?->statut_verification === 'refusee' && $residence->motif_refus)
        <x-ui.alerte type="erreur"><b>Refusée par l'équipe Avelis :</b> {{ $residence->motif_refus }}. Corrigez puis renvoyez pour vérification.</x-ui.alerte>
    @endif
    <div class="stepper st3" role="tablist" aria-label="Étapes">
        @foreach([1 => 'Ma résidence', 2 => 'Mes logements', 3 => 'Vérification et envoi'] as $n => $libelle)
            <button type="button" wire:click="allerEtape({{ $n }})" @disabled($n > 1 && ! $residence) class="st {{ $etape === $n ? 'on' : ($n < $etape ? 'fait' : '') }}" style="cursor:pointer" @if($etape === $n) aria-current="step" @endif>{{ $n }} {{ $libelle }}</button>
        @endforeach
    </div>

    <div style="max-width:880px;display:grid;gap:16px">
        {{-- 1. Résidence --}}
        @if($etape === 1)
        <form wire:submit="enregistrerResidence" class="carte-p">
            <h2>1. Ma résidence</h2>
            <div class="champ"><label class="etiquette" for="nom">Nom</label><input id="nom" wire:model="nom" required class="saisie">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="grid grid-cols-2 gap-3">
                <div class="champ"><label class="etiquette" for="commune">Commune</label><input id="commune" wire:model="commune" required class="saisie" placeholder="Ex. Cocody">@error('commune')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                <div class="champ"><label class="etiquette" for="quartier">Quartier</label><input id="quartier" wire:model="quartier" class="saisie" placeholder="Ex. Riviera">@error('quartier')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="champ"><label class="etiquette" for="latitude">Latitude (facultatif)</label><input id="latitude" inputmode="decimal" wire:model="latitude" class="saisie" placeholder="5.3600">@error('latitude')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                <div class="champ"><label class="etiquette" for="longitude">Longitude (facultatif)</label><input id="longitude" inputmode="decimal" wire:model="longitude" class="saisie" placeholder="-4.0083">@error('longitude')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            </div>
            <div class="champ"><label class="etiquette" for="reglement">Règlement de la maison</label><textarea id="reglement" rows="3" wire:model="reglement" class="saisie py-2"></textarea>@error('reglement')<p class="erreur-champ">{{ $message }}</p>@enderror</div>

            <div class="champ">
                <span class="etiquette">Mode de paiement</span>
                <div class="flex flex-wrap gap-2">
                    <label class="puce {{ $modePaiement === 'total' ? 'puce-active' : '' }}"><input type="radio" class="sr-only" wire:model.live="modePaiement" value="total">Tout payer en ligne</label>
                    <label class="puce {{ $modePaiement === 'acompte' ? 'puce-active' : '' }} {{ $plancher === null ? 'opacity-50' : '' }}"><input type="radio" class="sr-only" wire:model.live="modePaiement" value="acompte" @disabled($plancher === null)>Acompte en ligne</label>
                </div>
                @if($plancher === null)<p class="aide">L'acompte sera proposé dès que la commission et les frais de paiement seront réglés par Avelis.</p>@endif
                @error('modePaiement')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>
            @if($modePaiement === 'acompte')
                <div class="champ"><label class="etiquette" for="acompte">Acompte (%)</label><input id="acompte" inputmode="numeric" wire:model="pourcentageAcompte" class="saisie">
                    @if($plancher !== null)<span class="aide">Minimum autorisé : {{ $plancher }} % (la commission et les frais de paiement doivent être couverts).</span>@endif
                    @error('pourcentageAcompte')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            @endif
            <div class="rounded-xl bg-marque-50 p-3 text-[15px]">
                <p class="font-bold">Annulation : politique flexible</p>
                <p class="aide !text-[14px]">Remboursement intégral jusqu'à 24 h avant l'arrivée ; moins de 24 h avant, une nuit est retenue ; absence sans annulation : aucun remboursement. Une politique plus stricte pourra être proposée plus tard.</p>
            </div>
            <button class="btn-pl pleine" wire:loading.attr="disabled">{{ $residence ? 'Enregistrer la résidence' : 'Enregistrer et continuer' }}</button>
            @if($residence)<button type="button" wire:click="allerEtape(2)" class="btn-sec pleine">Étape suivante : mes logements →</button>@endif
        </form>
        @endif

        @if($residence && $etape === 2)
        {{-- 2. Logements --}}
        <div class="grid gap-4">
            <div class="carte-p">
                <div class="flex items-center justify-between gap-2">
                    <h2>2. Mes logements</h2>
                    <button type="button" wire:click="nouveauLogement" class="btn-sec"><x-ui.icone nom="plus" classe="h-4 w-4" /> Ajouter</button>
                </div>
                @forelse($residence->unites as $u)
                    <div class="rounded-xl border border-trait p-3 {{ $u->active ? '' : 'opacity-60' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-extrabold">{{ $u->nom }} @unless($u->active)<span class="aide">(retiré)</span>@endunless</p>
                                <p class="aide">{{ ucfirst($u->type) }} · {{ $u->capacite }} pers. · {{ ($p = $u->tarifs->firstWhere('type_periode', 'standard')) ? $fr($p->prix_nuit).' / nuit' : 'sans prix' }}</p>
                                <div class="mt-1 flex flex-wrap gap-1.5">@foreach($u->photos as $ph)<span class="relative"><img src="{{ $ph->urlMiniature() }}" alt="Photo du logement" width="56" height="56" class="h-14 w-14 rounded-lg object-cover" loading="lazy"><button type="button" wire:click="supprimerPhoto({{ $ph->id }})" wire:confirm="Supprimer cette photo ?" class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-red-700 text-xs font-bold text-white" aria-label="Supprimer la photo">×</button></span>@endforeach</div>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <button type="button" wire:click="modifierLogement({{ $u->id }})" class="rounded-lg bg-marque-50 px-3 py-2 text-sm font-bold text-marque-600">Modifier</button>
                                @if($u->active)<button type="button" wire:click="desactiverLogement({{ $u->id }})" wire:confirm="Retirer ce logement de la location ?" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-700">Retirer</button>
                                @else<button type="button" wire:click="reactiverLogement({{ $u->id }})" class="rounded-lg px-3 py-2 text-sm font-semibold text-marque-600">Remettre</button>@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="aide">Aucun logement pour l'instant. Touchez « Ajouter ».</p>
                @endforelse
            </div>

            @if($formulaireLogement)
            <form wire:submit="enregistrerLogement" class="carte-p">
                <h3 class="text-lg font-extrabold">{{ $uniteId ? 'Modifier le logement' : 'Un logement' }}</h3>
                <div class="champ"><label class="etiquette" for="uNom">Nom</label><input id="uNom" wire:model="uNom" required class="saisie" placeholder="Ex. Chambre climatisée 1">@error('uNom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="champ"><label class="etiquette" for="uType">Type</label><select id="uType" wire:model="uType" class="saisie"><option value="chambre">Chambre</option><option value="studio">Studio</option><option value="appartement">Appartement</option></select></div>
                    <div class="champ"><label class="etiquette" for="uCapacite">Personnes</label><input id="uCapacite" inputmode="numeric" wire:model="uCapacite" required class="saisie">@error('uCapacite')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="champ"><label class="etiquette" for="uPrix">Prix par nuit (FCFA)</label><input id="uPrix" inputmode="numeric" wire:model="uPrix" required class="saisie" placeholder="15 000">@error('uPrix')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ"><label class="etiquette" for="uPrixWeekEnd">Prix week-end (facultatif)</label><input id="uPrixWeekEnd" inputmode="numeric" wire:model="uPrixWeekEnd" class="saisie" placeholder="20 000">@error('uPrixWeekEnd')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                </div>
                <p class="aide">Le prix week-end s'applique aux nuits du vendredi et du samedi.</p>

                <div class="champ">
                    <span class="etiquette">Périodes spéciales (fêtes) — facultatif</span>
                    @foreach($uFetes as $i => $f)
                        <div class="grid gap-2 rounded-xl border border-trait p-2" wire:key="fete-{{ $i }}">
                            <input wire:model="uFetes.{{ $i }}.libelle" class="saisie" placeholder="Nom (ex. Noël)">
                            <div class="grid grid-cols-2 gap-2"><input type="date" wire:model="uFetes.{{ $i }}.debut" class="saisie" aria-label="Début"><input type="date" wire:model="uFetes.{{ $i }}.fin" class="saisie" aria-label="Fin"></div>
                            <div class="flex gap-2"><input inputmode="numeric" wire:model="uFetes.{{ $i }}.prix" class="saisie" placeholder="Prix par nuit"><button type="button" wire:click="retirerFete({{ $i }})" class="rounded-xl px-3 font-bold text-red-700">Retirer</button></div>
                            @error("uFetes.$i.debut")<p class="erreur-champ">{{ $message }}</p>@enderror @error("uFetes.$i.fin")<p class="erreur-champ">{{ $message }}</p>@enderror @error("uFetes.$i.prix")<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <button type="button" wire:click="ajouterFete" class="text-left font-semibold text-marque-600">+ Ajouter une période spéciale</button>
                </div>

                <div class="champ">
                    <span class="etiquette">Équipements</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($equipements as $e)
                            <label class="puce {{ in_array($e->id, array_map('intval', $uEquipements), true) ? 'puce-active' : '' }}"><input type="checkbox" class="sr-only" wire:model.live="uEquipements" value="{{ $e->id }}">{{ $e->libelle }}</label>
                        @endforeach
                    </div>
                </div>

                @error('uEquipements.*')<p class="erreur-champ">{{ $message }}</p>@enderror
                <div class="champ">
                    <label class="etiquette" for="photos">Photos ({{ $nbPhotos }}/{{ $maxPhotos }})</label>
                    <input id="photos" type="file" multiple accept="image/jpeg,image/png,image/webp" wire:model="nouvellesPhotos" class="saisie py-3">
                    <span class="aide">JPEG, PNG ou WebP, 5 Mo maximum chacune.</span>
                    <div wire:loading wire:target="nouvellesPhotos" class="aide">Envoi en cours…</div>
                    @error('nouvellesPhotos')<p class="erreur-champ">{{ $message }}</p>@enderror
                    @error('nouvellesPhotos.*')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button class="btn-pl pleine" wire:loading.attr="disabled">Enregistrer le logement</button>
                    <button type="button" wire:click="$set('formulaireLogement', false)" class="btn-sec pleine">Annuler</button>
                </div>
            </form>
            @endif
        </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap"><button type="button" wire:click="allerEtape(1)" class="btn-sec">← Ma résidence</button><button type="button" wire:click="allerEtape(3)" class="btn-pl">Étape suivante : envoi →</button></div>
        @endif
    </div>

    @if($residence && $etape === 3)
    <div class="pg-shell">
        <div class="grid gap-4" x-data="{ transferts: 0 }" x-on:livewire-upload-start="transferts++" x-on:livewire-upload-finish="transferts = Math.max(0, transferts - 1)" x-on:livewire-upload-error="transferts = Math.max(0, transferts - 1)" x-on:livewire-upload-cancel="transferts = Math.max(0, transferts - 1)">
            @if($profilGerant?->identiteValidee())
                <x-ui.alerte type="succes"><b>Votre identité est validée.</b> Aucune pièce personnelle à renvoyer pour cette résidence. <a class="underline" href="{{ route('gerant.profil') }}">Voir mon dossier</a></x-ui.alerte>
            @else
                <x-ui.alerte type="attention"><b>Votre identité doit d’abord être validée.</b> Votre brouillon reste disponible. Le certificat de résidence est facultatif. <a class="underline" href="{{ route('gerant.profil') }}">Compléter mon profil gérant →</a></x-ui.alerte>
            @endif
            <div class="carte-p">
                <h2>3. Vérifier avant d’envoyer</h2>
                <div class="pg-recap"><div><b>{{ $residence->nom }}</b><p class="aide">{{ $residence->commune }} · {{ $residence->unites->where('active', true)->count() }} logement(s) actif(s)</p></div><button type="button" wire:click="allerEtape(1)" class="btn-sec">Modifier</button></div>
                <div class="pg-doc">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h3>Justificatif du droit de gestion</h3><span class="pg-badge or">Facultatif</span></div>
                    <p class="aide">Un titre de propriété ou un mandat de gestion peut permettre l’attribution du badge de cette résidence, après contrôle par Avelis. Vous pouvez publier sans ce document.</p>
                    @if($justificatifActuel)
                        <p class="aide"><b>Document déjà enregistré</b> · {{ ['en_attente' => 'En cours de contrôle', 'accepte' => 'Validé', 'refuse' => 'Refusé'][$justificatifActuel->statut] }}</p>
                        @if($justificatifActuel->motif_refus)<p class="erreur-champ">{{ $justificatifActuel->motif_refus }}</p>@endif
                    @endif
                    <div class="champ"><label for="justif" class="etiquette">{{ $justificatifActuel ? 'Remplacer le justificatif (facultatif)' : 'Ajouter un justificatif (facultatif)' }}</label><input id="justif" wire:key="justif-{{ $versionPieces }}" type="file" accept="application/pdf,image/jpeg,image/png" wire:model="justificatif" class="saisie py-3"><p class="aide">PDF, JPEG ou PNG · 5 Mo maximum.</p>@error('justificatif')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    @error('pieces')<p class="erreur-champ" role="alert">{{ $message }}</p>@enderror
                    <button type="button" wire:click="envoyerPieces" class="btn-sec" wire:loading.attr="disabled" x-bind:disabled="transferts > 0">Enregistrer le justificatif seul</button>
                </div>
                <div wire:loading wire:target="justificatif" role="status" class="aide">Transfert du fichier en cours…</div>
                <div class="flex flex-wrap gap-3"><button type="button" wire:click="soumettre" class="btn-pl" wire:loading.attr="disabled" x-bind:disabled="transferts > 0" @disabled(! $profilGerant?->identiteValidee())>Envoyer pour validation →</button><a class="btn-sec" href="{{ route('gerant.residences') }}">Revenir à mes brouillons</a></div>
                <p class="aide">Les informations et logements enregistrés sont conservés. La résidence deviendra visible après validation de son annonce par Avelis.</p>
            </div>
        </div>
        <aside class="carte-p"><h2>Après votre envoi</h2><p class="aide">Vous revenez à « Mes résidences » avec une confirmation et le statut <b>En attente de validation</b>.</p><p class="aide">La certification du gérant et le badge de la résidence sont facultatifs. Ils ne remplacent pas la validation de l’annonce.</p></aside>
    </div>
    @endif
</div>
