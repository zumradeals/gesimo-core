<div class="grid gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">Mes résidences</h1>
        <a href="{{ route('gerant.residence.nouvelle') }}" class="btn"><x-ui.icone nom="plus" classe="h-5 w-5" /> Ajouter une résidence</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        @forelse($residences as $r)
            <a href="{{ route('gerant.residence', $r) }}" class="carte grid gap-1.5 hover:border-marque-500">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-extrabold">{{ $r->nom }}</h2>
                    @include('livewire.sejours.partiels.statut-residence', ['r' => $r])
                </div>
                <p class="aide">{{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif · {{ $r->unites_count }} logement{{ $r->unites_count > 1 ? 's' : '' }}</p>
                @if($r->statut_verification === 'refusee' && $r->motif_refus)<x-ui.alerte type="erreur">Refusée : {{ $r->motif_refus }}</x-ui.alerte>@endif
            </a>
        @empty
            <div class="carte grid place-items-center gap-3 py-10 text-center md:col-span-2">
                <x-ui.icone nom="batiment" classe="h-10 w-10 text-marque-500" />
                <p class="text-lg font-semibold">Vous n'avez pas encore de résidence.</p>
                <a href="{{ route('gerant.residence.nouvelle') }}" class="btn">Ajouter ma résidence</a>
            </div>
        @endforelse
    </div>
</div>
