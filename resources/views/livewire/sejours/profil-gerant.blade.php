<div class="mr-page ev-page">
    <div class="mr-entete"><div><h1>Mon profil gérant</h1><p class="petit">Vos informations et votre présentation publique.</p></div><a class="btn-sec" href="{{ route('gerant.profil.apercu') }}">Voir mon profil public ↗</a></div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    <x-ui.alerte type="info">Votre pièce d’identité et vos contrats de bail se déposent dans <a class="font-semibold underline" href="{{ route('gerant.verification') }}">Ma vérification</a> : une page, deux documents.</x-ui.alerte>
    <div class="ev-cols">
            <form wire:submit="enregistrer" class="mr-carte">
                <h2>Vos informations</h2><p class="aide">Votre identité reste privée. Choisissez séparément ce qui apparaît sur votre profil public.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="champ"><label class="etiquette" for="nomComplet">Nom complet *</label><input id="nomComplet" wire:model="nomComplet" required class="saisie" autocomplete="name" @readonly($profil->identiteValidee())>@error('nomComplet')<p class="erreur-champ">{{ $message }}</p>@enderror @if($profil->identiteValidee())<p class="aide">Nom de l’identité validée. Contactez Avelis pour le corriger.</p>@endif</div>
                    <div class="champ"><label class="etiquette" for="telephone">Téléphone du compte *</label><input id="telephone" type="tel" wire:model="telephone" required class="saisie" autocomplete="tel">@error('telephone')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ sm:col-span-2"><label class="etiquette" for="adresse">Adresse personnelle *</label><input id="adresse" wire:model="adresse" required class="saisie" autocomplete="street-address">@error('adresse')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ"><label class="etiquette" for="entreprise">Entreprise (facultatif)</label><input id="entreprise" wire:model="entreprise" class="saisie" autocomplete="organization">@error('entreprise')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ"><label class="etiquette" for="email">E-mail du compte *</label><input id="email" wire:model="email" type="email" required class="saisie" autocomplete="email">@error('email')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                </div>
                <h2 class="mt-4">Votre présentation publique</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="champ sm:col-span-2"><label class="etiquette" for="nomPublic">Nom affiché *</label><input id="nomPublic" wire:model="nomPublic" required class="saisie">@error('nomPublic')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ"><label class="etiquette" for="communePublique">Commune affichée (facultatif)</label><input id="communePublique" wire:model="communePublique" class="saisie">@error('communePublique')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ"><label class="etiquette" for="logo">Photo ou logo (facultatif)</label><input id="logo" type="file" wire:key="logo-{{ $versionFichiers }}" wire:model="logo" accept="image/jpeg,image/png,image/webp" class="saisie py-3"><span class="aide">JPEG, PNG ou WebP · 5 Mo maximum.</span>@error('logo')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ sm:col-span-2"><label class="etiquette" for="presentation">Présentation (facultatif)</label><textarea id="presentation" wire:model="presentation" rows="4" class="saisie py-3"></textarea>@error('presentation')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                </div>
                <p class="aide">Votre adresse personnelle, vos coordonnées de compte et vos documents ne deviennent pas publics.</p>
                <button class="btn-pl" wire:loading.attr="disabled">Enregistrer mon profil</button>
            </form>
        <aside class="mr-col">
            <section class="mr-carte rv-ivoire"><h2>Votre situation</h2>
                @if($profil->estVerifie())<p style="margin:0"><b>✓ Gérant vérifié</b></p><p class="petit" style="margin:0">Votre pièce d’identité et le contrat de bail de votre résidence sont validés.</p>
                @elseif($profil->identiteValidee())<p style="margin:0"><b>Pièce d’identité validée</b></p><p class="petit" style="margin:0">Ajoutez le contrat de bail de chaque résidence dans <a class="underline" href="{{ route('gerant.verification') }}">Ma vérification</a> pour obtenir le badge « Gérant vérifié ».</p>
                @else<p style="margin:0"><b>Identité à valider</b></p><p class="petit" style="margin:0">Vos brouillons restent disponibles. Faites valider votre pièce d’identité dans <a class="underline" href="{{ route('gerant.verification') }}">Ma vérification</a> avant l’envoi d’une annonce.</p>@endif
            </section>
            <section class="mr-carte"><h3>Ce que voit le public</h3><p class="petit" style="margin:0">Nom affiché, commune, présentation, photo ou logo, offres publiées. Jamais votre adresse personnelle, vos documents, votre téléphone ni votre e-mail.</p><a class="font-semibold text-marque-600" href="{{ route('gerant.profil.apercu') }}">Voir mon profil public ↗</a></section>
        </aside>
    </div>
</div>
