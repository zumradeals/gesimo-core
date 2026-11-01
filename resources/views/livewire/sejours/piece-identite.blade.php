<div class="rv-piece">
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif

    @if($valide && ! $remplacer)
        <div class="ligne"><span>État</span><span class="rv-badge ok">Fournie</span></div>
        <div class="ligne"><span>Type</span><b>{{ $piece->type->libelle() }}</b></div>
        @if($piece->valide_jusqu)<div class="ligne"><span>Valable jusqu'au</span><b>{{ $piece->valide_jusqu->translatedFormat('j F Y') }}</b></div>@endif
        <div class="ligne"><span>Ajoutée le</span><b>{{ $piece->updated_at->translatedFormat('j F Y') }}</b></div>
        <div class="rv-deux">
            <button type="button" wire:click="$set('remplacer', true)" class="btn-sec">Remplacer</button>
            <button type="button" wire:click="supprimer" wire:confirm="Supprimer votre pièce d'identité ?" class="btn-sec">Supprimer</button>
        </div>
        <p class="petit" style="margin:0">Si votre pièce arrive à expiration avant un séjour, nous vous demandons d'en ajouter une nouvelle.</p>
    @else
        @if($piece && $piece->estExpiree())<x-ui.alerte type="attention">Votre pièce enregistrée n'est plus valable. Ajoutez une pièce en cours de validité.</x-ui.alerte>@endif
        <form wire:submit="enregistrer" class="rv-formulaire-piece" novalidate>
            <div class="champ"><label class="etiquette" for="type-piece">Type de pièce</label>
                <select id="type-piece" wire:model="type" class="saisie">@foreach($types as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach</select>
                @error('type')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="valide-jusqu">Valable jusqu'au <span class="petit" style="font-weight:400">(facultatif)</span></label>
                <input id="valide-jusqu" type="date" min="{{ now()->addDay()->toDateString() }}" wire:model="valideJusqu" class="saisie">
                @error('valideJusqu')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="rv-depot">
                <b>Photo de la pièce (recto)</b>
                <p class="petit" style="margin:0">Posez la pièce sur une table, bien éclairée, tous les coins visibles.</p>
                <div class="rv-deux">
                    <label class="btn-sec rv-fichier">📷 Prendre une photo<input type="file" accept="image/*" capture="environment" wire:model="fichier" class="sr-only"></label>
                    <label class="btn-sec rv-fichier">Choisir un fichier<input type="file" accept="image/jpeg,image/png,application/pdf" wire:model="fichier" class="sr-only"></label>
                </div>
                <div wire:loading wire:target="fichier" class="petit">Chargement du fichier…</div>
                @if($fichier)<p class="petit" style="margin:0">✓ Fichier prêt : {{ $fichier->getClientOriginalName() }}</p>@endif
                @error('fichier')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>
            <x-ui.alerte type="info">🔒 Votre pièce est chiffrée. Seul le gérant de votre réservation peut la voir, à partir de la confirmation et jusqu'à quelques jours après votre départ. Chaque ouverture est enregistrée.</x-ui.alerte>
            <button type="submit" wire:loading.attr="disabled" class="btn-pl grand pleine">Envoyer ma pièce</button>
            @if($remplacer)<button type="button" wire:click="$set('remplacer', false)" class="rv-lien">Annuler</button>@endif
            @if($source === 'confirmation' && ! $valide)<p class="petit" style="margin:0;text-align:center">Vous pouvez aussi le faire plus tard depuis votre profil : nous vous enverrons un rappel avant l'arrivée.</p>@endif
        </form>
    @endif
</div>
