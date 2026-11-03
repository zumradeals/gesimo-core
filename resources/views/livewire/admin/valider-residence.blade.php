@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="mr-page ev-page">
    <p class="fil"><a href="{{ route('admin.residences') }}">‹ Résidences à valider</a></p>
    <div class="mr-entete"><div><h1>{{ $r->nom }}</h1></div>@include('livewire.sejours.partiels.statut-residence', ['r' => $r])</div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @error('decision')<x-ui.alerte type="erreur">{{ $message }}</x-ui.alerte>@enderror

    <div class="ev-cols">
        <div class="mr-col">
            <div class="mr-carte">
                <p class="aide">Gérant : {{ $r->gerant->nom }} · {{ \App\Noyau\Services\Telephone::afficher($r->gerant->telephone) }} · {{ $r->gerant->email }}</p>
                @if($r->gerant->profilGerant)<a class="font-semibold text-marque-600 underline" href="{{ route('admin.gerant', $r->gerant->profilGerant) }}">Contrôler le dossier d’identité du gérant →</a><p class="aide">Identité : {{ $r->gerant->profilGerant->identiteValidee() ? 'validée' : 'à valider' }} · Gérant vérifié : {{ $r->gerant->profilGerant->estVerifie() ? 'oui' : 'pas encore' }}</p>@else<p class="erreur-champ">Le dossier d’identité du gérant doit être complété.</p>@endif
                <p>{{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif</p>
                <p class="aide !text-[15px]">Paiement : {{ $r->mode_paiement === 'acompte' ? 'acompte de '.$r->pourcentage_acompte.' %' : 'tout en ligne' }} · annulation {{ $r->politique_annulation }}</p>
                @if($r->reglement)<p class="aide !text-[15px]">Règlement : {{ $r->reglement }}</p>@endif
            </div>
            <div class="mr-carte"><h2 class="mb-2 font-extrabold">Photos</h2>
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">@forelse($r->photos as $p)<img src="{{ $p->urlMiniature() }}" alt="Photo de la résidence" width="480" height="320" class="h-28 w-full rounded-lg object-cover" loading="lazy" decoding="async">@empty<p class="aide">Aucune photo.</p>@endforelse</div></div>
            <div class="mr-carte"><h2 class="mb-2 font-extrabold">Logements</h2>
                @foreach($r->unites as $u)<div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>{{ $u->nom }} <span class="aide">({{ $u->type }}, {{ $u->capacite }} pers.)</span></span><span>{{ ($p = $u->tarifs->firstWhere('type_periode', 'standard')) ? $fr($p->prix_nuit) : 'sans prix' }}</span></div>@endforeach</div>
            <div class="mr-carte"><h2>Contrat de bail <span class="mr-badge att">Obligatoire</span></h2>
                @forelse($r->documents->where('type', 'justificatif_droit_gestion')->whereNull('remplace_le') as $d)
                    <div class="grid gap-2 border-b border-trait py-3"><b>{{ $d->libelle() }}</b><p class="aide">{{ $d->statut }}</p><a href="{{ route('admin.document', $d) }}" target="_blank" rel="noopener" class="font-semibold text-marque-600 underline">Consulter le document privé ↗</a><button class="btn-sec" wire:click="refuserJustificatif({{ $d->id }})">Refuser ce document seul</button></div>
                @empty<p class="aide">Aucun contrat de bail déposé : la résidence ne peut pas être mise en ligne.</p>@endforelse
                <div class="champ"><label for="motifGestion" class="etiquette">Motif de refus du document</label><textarea id="motifGestion" rows="2" wire:model="motifGestion" class="saisie py-2"></textarea>@error('motifGestion')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                @if($r->estVerifiee())<button class="btn-pl" wire:click="accorderBadge" wire:confirm="Avez-vous contrôlé le droit de gérer cette résidence ?" wire:loading.attr="disabled">Valider le contrat de bail et accorder le badge</button>@endif
            </div>
        </div>

        <div class="mr-carte">
            <h2>Décision sur l’annonce</h2>
            @php $bailEtat = \App\Sejours\Services\EtatDocument::de($r->documents->where('type', 'justificatif_droit_gestion')->whereNull('remplace_le')->sortByDesc('id')->first(), $r->aBadgeGestion()); $idEtat = \App\Sejours\Services\EtatDocument::de($r->gerant->profilGerant?->dernierDocument('identite'), (bool) $r->gerant->profilGerant?->identiteValidee()); @endphp
            <div class="gq-recap"><div class="ligne"><span>Pièce d’identité du gérant</span><span class="mr-badge gq-b-{{ \App\Sejours\Services\EtatDocument::classe($idEtat) }}">{{ ['a_fournir' => 'Absente', 'valide' => 'Validée'][$idEtat] ?? \App\Sejours\Services\EtatDocument::libelle($idEtat) }}</span></div><div class="ligne"><span>Contrat de bail</span><span class="mr-badge gq-b-{{ \App\Sejours\Services\EtatDocument::classe($bailEtat) }}">{{ $bailEtat === 'a_fournir' ? 'Absent' : \App\Sejours\Services\EtatDocument::libelle($bailEtat) }}</span></div></div><p class="aide">Deux documents sont obligatoires pour mettre en ligne : la pièce d’identité du profil et le contrat de bail (justificatif de gestion) de cette résidence.</p>
            <label class="puce {{ $badge ? 'puce-active' : '' }}"><input type="checkbox" class="sr-only" wire:model.live="badge">J’ai contrôlé le contrat de bail : le valider (badge « Gérant vérifié »)</label>
            <button wire:click="valider" wire:confirm="Valider et mettre cette résidence en ligne ?" class="btn-pl pleine" wire:loading.attr="disabled">Valider et mettre en ligne</button>
            <div class="champ"><label class="etiquette" for="motif">Motif du refus</label><textarea id="motif" rows="2" wire:model="motifRefus" class="saisie py-2" placeholder="Ex. Photos du logement à compléter"></textarea>@error('motifRefus')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button wire:click="refuser" class="btn-sec rouge-b2 pleine">Refuser</button>
            <p class="aide">Chaque décision est écrite dans le journal d'audit.</p>
        </div>
    </div>
</div>
