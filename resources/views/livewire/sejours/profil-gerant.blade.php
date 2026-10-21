<div class="grid gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3"><div class="adm-top"><div><h1>Mon profil gérant</h1><p>Vos informations et vos documents, une seule fois pour toutes vos résidences.</p></div></div><a class="btn-sec" href="{{ route('gerant.profil.apercu') }}">Voir mon profil public ↗</a></div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    <div class="pg-shell">
        <div>
            <div class="pg-tabs" role="tablist" aria-label="Mon profil"><button type="button" wire:click="$set('onglet', 'informations')" role="tab" aria-selected="{{ $onglet === 'informations' ? 'true' : 'false' }}">Mes informations</button><button type="button" wire:click="$set('onglet', 'documents')" role="tab" aria-selected="{{ $onglet !== 'informations' ? 'true' : 'false' }}">Mes documents</button></div>
            @if($onglet === 'informations')
            <form wire:submit="enregistrer" class="carte-p">
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
            @else
            <form wire:submit="envoyerDocuments" class="grid gap-4" x-data="{ transferts: 0 }" x-on:livewire-upload-start="transferts++" x-on:livewire-upload-finish="transferts = Math.max(0, transferts - 1)" x-on:livewire-upload-error="transferts = Math.max(0, transferts - 1)" x-on:livewire-upload-cancel="transferts = Math.max(0, transferts - 1)">
                @if($profil->reprise_a_revoir)<x-ui.alerte type="attention">Plusieurs pièces différentes proviennent de vos anciennes résidences. Avelis doit contrôler leur reprise ; les fichiers déjà fournis sont conservés.</x-ui.alerte>@endif
                @foreach(['identite' => 'Pièce d’identité', 'certificat_residence' => 'Certificat de résidence'] as $type => $titre)
                    @php $dernier = $documents->firstWhere('type', $type); $valide = $profil->documentValide($type); @endphp
                    <div class="carte-p" wire:key="doc-{{ $type }}">
                        <h2>{{ $titre }}</h2>
                        <div class="flex flex-wrap gap-2"><span class="pg-badge {{ $type === 'identite' ? 'neutre' : 'or' }}">{{ $type === 'identite' ? 'Obligatoire pour publier' : 'Facultatif · Pour devenir gérant certifié' }}</span>@if($valide)<span class="pg-badge">✓ Validé par Avelis</span>@endif</div>
                        <p class="aide">{{ $type === 'identite' ? 'Votre pièce identifie la personne responsable des résidences. Elle est contrôlée une seule fois pour votre dossier gérant.' : 'Ajoutez le certificat de résidence délivré par un commissariat en Côte d’Ivoire. Après validation avec votre pièce d’identité, vous obtenez la mention « Gérant certifié ».' }}</p>
                        @if($dernier)<div class="pg-doc"><b>Document enregistré</b><p class="aide">{{ ['en_attente' => 'En cours de contrôle', 'accepte' => 'Validé par Avelis', 'refuse' => 'Refusé'][$dernier->statut] }}</p>@if($dernier->motif_refus)<p class="erreur-champ">{{ $dernier->motif_refus }}</p>@endif @if($valide && $dernier->id !== $valide->id)<p class="aide">Votre précédent document validé reste valable pendant l’examen de son remplacement.</p>@endif</div>@endif
                        <div class="champ"><label class="etiquette" for="{{ $type }}">{{ $dernier ? 'Fournir un nouveau document' : 'Choisir mon document' }}</label><input id="{{ $type }}" wire:key="fichier-{{ $type }}-{{ $versionFichiers }}" type="file" wire:model="{{ $type }}" accept="application/pdf,image/jpeg,image/png" class="saisie py-3"><span class="aide">PDF, JPEG ou PNG · 5 Mo maximum.</span>@error($type)<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                        @if($type === 'certificat_residence')<p class="aide"><b>Vous pouvez publier sans ce certificat.</b> Son simple dépôt ne donne pas la certification.</p>@endif
                    </div>
                @endforeach
                @error('documents')<p class="erreur-champ" role="alert">{{ $message }}</p>@enderror
                <div wire:loading wire:target="identite,certificat_residence" role="status" class="aide">Transfert en cours… Attendez la fin avant d’envoyer.</div>
                <button class="btn-pl" wire:loading.attr="disabled" x-bind:disabled="transferts > 0">Envoyer les documents pour contrôle</button>
                <x-ui.alerte type="info"><b>Vos documents restent privés.</b> Seule l’équipe Avelis autorisée peut les consulter. Aucun document ni votre adresse personnelle n’apparaît sur votre profil public.</x-ui.alerte>
            </form>
            @endif
        </div>
        <aside class="grid gap-4">
            <div class="pg-situation"><p class="aide">VOTRE SITUATION</p><h2>{{ $profil->identiteValidee() ? 'Prêt à publier' : 'Identité à valider' }}</h2><p>{{ $profil->identiteValidee() ? 'Votre pièce d’identité est validée. Vous pouvez soumettre vos résidences à Avelis.' : 'Vos brouillons restent disponibles. Faites valider votre pièce d’identité avant l’envoi d’une annonce.' }}</p>@if($profil->estCertifie())<span class="pg-badge or">✓ Gérant certifié</span>@else<p>Certification complémentaire facultative.</p>@endif<a href="{{ route('gerant.residences') }}" class="btn-sec">Gérer mes résidences →</a></div>
            <div class="carte-p"><h2>Deux reconnaissances distinctes</h2><p class="aide"><b>Gérant certifié</b><br>Votre identité et votre certificat de résidence ont été contrôlés.</p><p class="aide"><b>Résidence avec badge</b><br>Un justificatif du droit de gérer cette résidence a été contrôlé.</p><a class="font-semibold text-marque-600" href="{{ route('gerant.profil.apercu') }}">Voir mon profil public ↗</a></div>
        </aside>
    </div>
</div>
