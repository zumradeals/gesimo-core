<div class="mr-page ev-page">
    <div class="mr-entete"><div><h1>Résidences à valider ({{ $aVerifier->count() }})</h1></div></div>
    <div class="mr-carte"><h2>Dossiers des gérants à contrôler ({{ $gerantsAControler->count() }})</h2><p class="aide">L’identité se contrôle une fois par gérant.</p>@forelse($gerantsAControler as $p)<a class="pg-recap" href="{{ route('admin.gerant', $p) }}"><b>{{ $p->nom_public }}</b><span class="font-semibold text-marque-600">Contrôler le dossier →</span></a>@empty<p class="aide">Aucun dossier gérant en attente.</p>@endforelse</div>
    @if($justificatifsAControler->isNotEmpty())<div class="mr-carte"><h2>Contrats de bail à contrôler ({{ $justificatifsAControler->count() }})</h2><p class="aide">Le contrat de bail est obligatoire pour mettre une résidence en ligne.</p>@foreach($justificatifsAControler as $r)<a class="pg-recap" href="{{ route('admin.residence', $r) }}"><b>{{ $r->nom }}</b><span class="font-semibold text-marque-600">Contrôler le contrat de bail →</span></a>@endforeach</div>@endif
    <div class="gq-cartes">
        @forelse($aVerifier as $r)
            <a href="{{ route('admin.residence', $r) }}" class="mr-carte" style="text-decoration:none;color:inherit">
                <div class="flex items-center justify-between gap-2"><h2>{{ $r->nom }}</h2><span class="rounded-full bg-amber-100 px-3 py-0.5 text-[13px] font-bold text-amber-800">En attente</span></div>
                <p class="aide">{{ $r->commune }} · gérant : {{ $r->gerant->nom }} · envoyée {{ $r->soumise_le->diffForHumans() }}</p>
            </a>
        @empty
            <x-ui.alerte type="info" class="md:col-span-2">Aucune résidence en attente de vérification.</x-ui.alerte>
        @endforelse
    </div>

    @if($recentes->isNotEmpty())
        <h2>Décisions récentes</h2>
        <div class="gq-cartes">
            @foreach($recentes as $r)
                <a href="{{ route('admin.residence', $r) }}" class="mr-carte" style="text-decoration:none;color:inherit">
                    <div class="flex items-center justify-between gap-2"><h3 class="font-extrabold">{{ $r->nom }}</h3>@include('livewire.sejours.partiels.statut-residence', ['r' => $r])</div>
                    <p class="aide">{{ $r->commune }} · {{ $r->gerant->nom }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
