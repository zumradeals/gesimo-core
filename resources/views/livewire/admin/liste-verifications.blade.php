<div class="grid gap-5">
    <div class="adm-top"><div><h1>Résidences à vérifier ({{ $aVerifier->count() }})</h1></div></div>
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
