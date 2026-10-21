<div class="grid gap-5">
    <a href="{{ route('admin.residences') }}" class="font-semibold text-marque-600">← Dossiers à vérifier</a>
    <div class="adm-top"><div><h1>Dossier gérant · {{ $p->nom_public }}</h1></div></div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @error('documents')<x-ui.alerte type="erreur">{{ $message }}</x-ui.alerte>@enderror
    @if($p->reprise_a_revoir)<x-ui.alerte type="attention">Plusieurs pièces différentes proviennent d’anciennes résidences. Contrôlez les documents et acceptez explicitement l’identité à retenir. Aucune pièce n’a été choisie automatiquement.</x-ui.alerte>@endif
    <div class="pg-shell">
        <div class="grid gap-4">
            <div class="carte-p"><h2>Informations privées</h2><form wire:submit="corrigerNom" class="grid gap-2"><label for="nomComplet" class="etiquette">Nom complet privé</label><input id="nomComplet" required wire:model="nomComplet" class="saisie">@error('nomComplet')<p class="erreur-champ">{{ $message }}</p>@enderror<button class="btn-sec" wire:confirm="Confirmer que ce nom correspond au document d’identité contrôlé ?">Corriger le nom après contrôle</button></form><p><b>Adresse :</b> {{ $p->adresse ?: 'Non renseignée' }}</p><p><b>Entreprise :</b> {{ $p->entreprise ?: 'Non renseignée' }}</p><p class="aide">{{ $p->utilisateur->telephone }} · {{ $p->utilisateur->email }}</p></div>
            @forelse($documents as $d)
                <div class="carte-p" wire:key="document-{{ $d->id }}"><div class="flex flex-wrap items-center justify-between gap-3"><h2>{{ $d->libelle() }}</h2><span class="pg-badge {{ $d->statut === 'accepte' ? '' : 'neutre' }}">{{ $d->remplace_le ? 'Historique conservé' : ['en_attente' => 'En attente', 'accepte' => 'Accepté', 'refuse' => 'Refusé'][$d->statut] }}</span></div><a href="{{ route('admin.document-gerant', $d) }}" class="font-semibold text-marque-600 underline" target="_blank" rel="noopener">Consulter le document privé ↗</a>@if($d->motif_refus)<p class="erreur-champ">{{ $d->motif_refus }}</p>@endif
                    @unless($d->remplace_le)<div class="flex flex-wrap gap-3">@if($d->statut !== 'accepte')<button wire:click="accepter({{ $d->id }})" wire:confirm="Confirmer que vous avez contrôlé ce document et qu’il correspond au gérant ?" class="btn-pl" wire:loading.attr="disabled">Accepter ce document</button>@endif<button wire:click="refuser({{ $d->id }})" wire:confirm="Refuser ce document ? Le refus de l’identité principale peut rendre les annonces non publiables. Un remplacement refusé ne retire pas une ancienne preuve encore validée." class="btn-sec" wire:loading.attr="disabled">Refuser ce document</button></div>@endunless
                </div>
            @empty<x-ui.alerte type="info">Aucun document fourni.</x-ui.alerte>@endforelse
        </div>
        <aside class="carte-p"><h2>Contrôles indépendants</h2><p class="aide">L’identité acceptée permet la publication. Le certificat de résidence accepté en plus de l’identité donne la certification du gérant.</p><p class="aide">Le refus du certificat seul ne bloque pas les annonces. Le justificatif de gestion se contrôle depuis la résidence concernée.</p><div class="champ"><label for="motif" class="etiquette">Motif du refus documentaire</label><textarea id="motif" wire:model="motif" rows="4" class="saisie py-3"></textarea>@error('motif')<p class="erreur-champ">{{ $message }}</p>@enderror</div><p class="aide">Chaque consultation et décision est journalisée.</p></aside>
    </div>
</div>
