@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="grid gap-4">
    <a href="{{ route('admin.residences') }}" class="inline-flex items-center gap-1 font-semibold text-marque-600"><x-ui.icone nom="fleche-gauche" classe="h-5 w-5" /> Résidences à vérifier</a>
    <div class="flex flex-wrap items-center justify-between gap-2"><div class="adm-top"><div><h1>{{ $r->nom }}</h1></div></div>@include('livewire.sejours.partiels.statut-residence', ['r' => $r])</div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @error('decision')<x-ui.alerte type="erreur">{{ $message }}</x-ui.alerte>@enderror

    <div class="grid gap-4 lg:grid-cols-[1.3fr_1fr] lg:items-start">
        <div class="grid gap-4">
            <div class="carte-p">
                <p class="aide">Gérant : {{ $r->gerant->nom }} · {{ \App\Noyau\Services\Telephone::afficher($r->gerant->telephone) }} · {{ $r->gerant->email }}</p>
                @if($r->gerant->profilGerant)<a class="font-semibold text-marque-600 underline" href="{{ route('admin.gerant', $r->gerant->profilGerant) }}">Contrôler le dossier d’identité du gérant →</a><p class="aide">Identité : {{ $r->gerant->profilGerant->identiteValidee() ? 'validée' : 'à valider' }} · Certification : {{ $r->gerant->profilGerant->estCertifie() ? 'accordée' : 'non accordée (facultative)' }}</p>@else<p class="erreur-champ">Le dossier d’identité du gérant doit être complété.</p>@endif
                <p>{{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif</p>
                <p class="aide !text-[15px]">Paiement : {{ $r->mode_paiement === 'acompte' ? 'acompte de '.$r->pourcentage_acompte.' %' : 'tout en ligne' }} · annulation {{ $r->politique_annulation }}</p>
                @if($r->reglement)<p class="aide !text-[15px]">Règlement : {{ $r->reglement }}</p>@endif
            </div>
            <div class="carte-p"><h2 class="mb-2 font-extrabold">Photos</h2>
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">@forelse($r->photos as $p)<img src="{{ $p->urlMiniature() }}" alt="Photo de la résidence" width="480" height="320" class="h-28 w-full rounded-lg object-cover" loading="lazy" decoding="async">@empty<p class="aide">Aucune photo.</p>@endforelse</div></div>
            <div class="carte-p"><h2 class="mb-2 font-extrabold">Logements</h2>
                @foreach($r->unites as $u)<div class="flex justify-between border-b border-dashed border-trait py-1.5"><span>{{ $u->nom }} <span class="aide">({{ $u->type }}, {{ $u->capacite }} pers.)</span></span><span>{{ ($p = $u->tarifs->firstWhere('type_periode', 'standard')) ? $fr($p->prix_nuit) : 'sans prix' }}</span></div>@endforeach</div>
            <div class="carte-p"><h2>Justificatifs de gestion (facultatifs)</h2>
                @forelse($r->documents->where('type', 'justificatif_droit_gestion')->whereNull('remplace_le') as $d)
                    <div class="grid gap-2 border-b border-trait py-3"><b>{{ $d->libelle() }}</b><p class="aide">{{ $d->statut }}</p><a href="{{ route('admin.document', $d) }}" target="_blank" rel="noopener" class="font-semibold text-marque-600 underline">Consulter le document privé ↗</a><button class="btn-sec" wire:click="refuserJustificatif({{ $d->id }})">Refuser ce justificatif seul</button></div>
                @empty<p class="aide">Aucun justificatif : cela n’empêche pas la publication.</p>@endforelse
                <div class="champ"><label for="motifGestion" class="etiquette">Motif de refus du justificatif</label><textarea id="motifGestion" rows="2" wire:model="motifGestion" class="saisie py-2"></textarea>@error('motifGestion')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                @if($r->estVerifiee())<button class="btn-pl" wire:click="accorderBadge" wire:confirm="Avez-vous contrôlé le droit de gérer cette résidence ?" wire:loading.attr="disabled">Valider le justificatif et accorder le badge</button>@endif
            </div>
        </div>

        <div class="carte-p">
            <h2>Décision sur l’annonce</h2><p class="aide">La pièce d’identité du profil doit être validée. Le certificat de résidence et le justificatif de gestion sont facultatifs.</p>
            <label class="puce {{ $badge ? 'puce-active' : '' }}"><input type="checkbox" class="sr-only" wire:model.live="badge">J’ai contrôlé le justificatif de gestion : accorder le badge à cette résidence</label>
            <button wire:click="valider" wire:confirm="Valider et mettre cette résidence en ligne ?" class="btn-pl pleine" wire:loading.attr="disabled">Valider et mettre en ligne</button>
            <div class="champ"><label class="etiquette" for="motif">Motif du refus</label><textarea id="motif" rows="2" wire:model="motifRefus" class="saisie py-2" placeholder="Ex. Photos du logement à compléter"></textarea>@error('motifRefus')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <button wire:click="refuser" class="btn-sec rouge-b2 pleine">Refuser</button>
            <p class="aide">Chaque décision est écrite dans le journal d'audit.</p>
        </div>
    </div>
</div>
