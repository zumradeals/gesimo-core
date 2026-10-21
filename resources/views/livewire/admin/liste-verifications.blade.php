<div class="grid gap-5">
    <div class="adm-top"><div><h1>Résidences à vérifier ({{ $aVerifier->count() }})</h1></div></div>
    <div class="carte-p"><h2>Dossiers des gérants à contrôler ({{ $gerantsAControler->count() }})</h2><p class="aide">L’identité se contrôle une fois par gérant. La certification complémentaire reste facultative.</p>@forelse($gerantsAControler as $p)<a class="pg-recap" href="{{ route('admin.gerant', $p) }}"><b>{{ $p->nom_public }}</b><span class="font-semibold text-marque-600">Contrôler le dossier →</span></a>@empty<p class="aide">Aucun dossier gérant en attente.</p>@endforelse</div>
    @if($justificatifsAControler->isNotEmpty())<div class="carte-p"><h2>Justificatifs de gestion à contrôler</h2><p class="aide">Leur contrôle est indépendant de la publication.</p>@foreach($justificatifsAControler as $r)<a class="pg-recap" href="{{ route('admin.residence', $r) }}"><b>{{ $r->nom }}</b><span class="font-semibold text-marque-600">Contrôler le justificatif →</span></a>@endforeach</div>@endif
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($aVerifier as $r)
            <a href="{{ route('admin.residence', $r) }}" class="carte grid gap-1 hover:border-marque-500">
                <div class="flex items-center justify-between gap-2"><h2>{{ $r->nom }}</h2><span class="rounded-full bg-amber-100 px-3 py-0.5 text-[13px] font-bold text-amber-800">En attente</span></div>
                <p class="aide">{{ $r->commune }} · gérant : {{ $r->gerant->nom }} · envoyée {{ $r->soumise_le->diffForHumans() }}</p>
            </a>
        @empty
            <x-ui.alerte type="info" class="md:col-span-2">Aucune résidence en attente de vérification.</x-ui.alerte>
        @endforelse
    </div>

    @if($recentes->isNotEmpty())
        <h2>Décisions récentes</h2>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach($recentes as $r)
                <a href="{{ route('admin.residence', $r) }}" class="carte grid gap-1 hover:border-marque-500">
                    <div class="flex items-center justify-between gap-2"><h3 class="font-extrabold">{{ $r->nom }}</h3>@include('livewire.sejours.partiels.statut-residence', ['r' => $r])</div>
                    <p class="aide">{{ $r->commune }} · {{ $r->gerant->nom }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
