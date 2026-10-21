<div class="grid gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="adm-top"><div><h1>Mes résidences</h1></div></div>
        <a href="{{ route('gerant.residence.nouvelle') }}" class="btn-pl"><x-ui.icone nom="plus" classe="h-5 w-5" /> Ajouter une résidence</a>
    </div>

    @if(session('message'))
        <div class="pg-confirmation" role="status"><span class="pg-confirmation-icon" aria-hidden="true">✓</span><div><h2>{{ session('message') }}</h2>@if(session('residence_soumise'))<p class="aide">Votre envoi est terminé. Aucun nouvel envoi n’est nécessaire. Retrouvez ci-dessous le suivi du dossier.</p><p class="aide">✓ Envoyée → Examen par Avelis → Publication après accord</p>@endif</div></div>
    @endif
    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        @forelse($residences as $r)
            <a href="{{ route('gerant.residence', $r) }}" class="carte grid gap-1.5 hover:border-marque-500">
                <div class="flex items-center justify-between">
                    <h2>{{ $r->nom }}</h2>
                    @include('livewire.sejours.partiels.statut-residence', ['r' => $r])
                </div>
                <p class="aide">{{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif · {{ $r->unites_count }} logement{{ $r->unites_count > 1 ? 's' : '' }}</p>
                @if($r->soumise_le && $r->statut_verification === 'en_attente')<span class="font-semibold text-marque-600">Suivre mon dossier →</span>@endif
                @if($r->statut_verification === 'refusee' && $r->motif_refus)<x-ui.alerte type="erreur">Refusée : {{ $r->motif_refus }}</x-ui.alerte>@endif
            </a>
        @empty
            <div class="carte grid place-items-center gap-3 py-10 text-center md:col-span-2">
                <x-ui.icone nom="batiment" classe="h-10 w-10 text-marque-500" />
                <p class="text-lg font-semibold">Vous n'avez pas encore de résidence.</p>
                <a href="{{ route('gerant.residence.nouvelle') }}" class="btn-pl">Ajouter ma résidence</a>
            </div>
        @endforelse
    </div>
</div>
