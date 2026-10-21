<div class="grid gap-4">
    <div class="adm-top"><div><h1>Calendrier</h1></div></div>

    @if($residences->isEmpty())
        <div class="carte-p">
            <x-ui.icone nom="calendrier" classe="h-10 w-10 text-marque-500" />
            <p class="text-lg font-semibold">Aucune résidence à afficher.</p>
            @can('create', \App\Sejours\Models\Residence::class)<a href="{{ route('gerant.residence.nouvelle') }}" class="btn-pl">Ajouter ma résidence</a>@endcan
        </div>
    @else
    <div class="grid gap-3 md:grid-cols-2 lg:max-w-3xl">
        <div class="champ"><label class="etiquette" for="residence">Résidence</label>
            <select id="residence" wire:model.live="residenceId" class="saisie">@foreach($residences as $r)<option value="{{ $r->id }}">{{ $r->nom }} ({{ $r->commune }})</option>@endforeach</select></div>
        @if($unite !== null)<div class="champ"><label class="etiquette" for="unite">Logement</label>
            <select id="unite" wire:model.live="uniteId" class="saisie">@foreach($unites as $u)<option value="{{ $u->id }}">{{ $u->nom }}</option>@endforeach</select></div>@endif
    </div>
    @if($unite === null)
        <x-ui.alerte type="attention">Cette résidence n'a pas encore de logement actif.</x-ui.alerte>
        @can('update', $residences->firstWhere('id', $residenceId))
            <a href="{{ route('gerant.residence', $residenceId) }}" class="btn-sec">Gérer les logements de cette résidence</a>
        @endcan
    @else
    @php
        $jours = $debutMois->daysInMonth;
        $couleurs = ['confirmee' => 'bg-green-100 border-green-300', 'attente' => 'bg-amber-100 border-amber-300', 'tel' => 'bg-blue-100 border-blue-300', 'bloque' => 'bg-slate-200 border-slate-300 line-through'];
    @endphp


    <div class="grid gap-4 xl:grid-cols-[1.3fr_1fr] xl:items-start">
        <div class="carte-p">
            <div class="mb-2 flex items-center justify-between">
                <button type="button" wire:click="moisPrecedent" class="rounded-xl p-2 hover:bg-fond" aria-label="Mois précédent"><x-ui.icone nom="fleche-gauche" /></button>
                <b class="text-lg capitalize">{{ $debutMois->translatedFormat('F Y') }}</b>
                <button type="button" wire:click="moisSuivant" class="rounded-xl p-2 hover:bg-fond" aria-label="Mois suivant"><x-ui.icone nom="fleche-droite" /></button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-[13px] lg:gap-1.5">
                @foreach(['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'] as $nomJour)<div class="font-bold text-gris-doux">{{ $nomJour }}</div>@endforeach
                @for($i = 0; $i < $decalage; $i++)<div></div>@endfor
                @for($j = 1; $j <= $jours; $j++)
                    @php $date = $debutMois->day($j)->toDateString(); $n = $nuits[$date] ?? null; @endphp
                    <div class="flex min-h-[48px] flex-col items-center justify-center rounded-lg border p-0.5 lg:min-h-[64px] {{ $n ? $couleurs[$n['type']] : 'border-trait bg-white' }}">
                        <b>{{ $j }}</b>@if($n)<span class="text-[10px] leading-tight">{{ $n['libelle'] }}</span>@endif
                    </div>
                @endfor
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-[13px]">
                <span><i class="mr-1 inline-block h-3 w-3 rounded bg-green-200 align-[-1px]"></i>Confirmée</span><span><i class="mr-1 inline-block h-3 w-3 rounded bg-amber-200 align-[-1px]"></i>En attente</span>
                <span><i class="mr-1 inline-block h-3 w-3 rounded bg-blue-200 align-[-1px]"></i>Prise par téléphone</span><span><i class="mr-1 inline-block h-3 w-3 rounded bg-slate-300 align-[-1px]"></i>Bloqué</span><span><i class="mr-1 inline-block h-3 w-3 rounded border border-trait bg-white align-[-1px]"></i>Libre</span>
            </div>
        </div>

        <div class="grid gap-4">
            @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
            @if(session('message'))<x-ui.alerte type="succes">{{ session('message') }}</x-ui.alerte>@endif
            @if($peutSaisirTelephone)<a href="{{ route('gerant.telephone', ['unite' => $unite->id]) }}" class="btn-pl pleine">Ajouter une réservation par téléphone</a>@endif

            <form wire:submit="bloquer" class="carte-p">
                <h2>Bloquer des dates</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="champ"><label class="etiquette" for="debut">Du</label><input id="debut" type="date" wire:model="debut" class="saisie">@error('debut')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                    <div class="champ"><label class="etiquette" for="fin">Au (dernier jour bloqué)</label><input id="fin" type="date" wire:model="fin" class="saisie">@error('fin')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
                </div>
                <div class="champ"><label class="etiquette" for="motif">Raison (facultatif)</label><input id="motif" wire:model="motif" class="saisie" placeholder="Ex. Travaux"></div>
                @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
                <button class="btn-pl pleine" wire:loading.attr="disabled">Bloquer</button>
            </form>

            <div class="carte-p">
                <h2>Ce mois-ci</h2>
                @forelse($reservations as $r)
                    <div class="flex items-start justify-between gap-2 border-b border-dashed border-trait pb-2">
                        <div>
                            <p class="font-bold">{{ $r->client?->nom ?? $r->nom_client }}</p>
                            <p class="aide">{{ $r->date_arrivee->translatedFormat('j M') }} → {{ $r->date_depart->translatedFormat('j M') }} · <x-ui.badge-statut :statut="$r->statut" /></p>
                        </div>
                        @if($r->origine === 'hors_plateforme' && $peutSaisirTelephone)
                            <button type="button" wire:click="annulerHorsPlateforme({{ $r->id }})" wire:confirm="Annuler cette réservation par téléphone ?" class="text-sm font-semibold text-red-700">Annuler</button>
                        @endif
                    </div>
                @empty
                @endforelse
                @foreach($blocages as $b)
                    <div class="flex items-start justify-between gap-2 border-b border-dashed border-trait pb-2">
                        <div><p class="font-bold">Bloqué{{ $b->motif ? ' · '.$b->motif : '' }}</p><p class="aide">{{ $b->date_debut->translatedFormat('j M') }} → {{ $b->date_fin->copy()->subDay()->translatedFormat('j M') }} (inclus)</p></div>
                        <button type="button" wire:click="debloquer({{ $b->id }})" class="text-sm font-semibold text-red-700">Retirer</button>
                    </div>
                @endforeach
                @if($reservations->isEmpty() && $blocages->isEmpty())<p class="aide">Rien ce mois-ci : le logement est libre.</p>@endif
            </div>
        </div>
    </div>
    @endif
    @endif
</div>
