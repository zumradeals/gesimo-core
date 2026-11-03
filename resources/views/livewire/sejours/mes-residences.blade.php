<div class="mr-page ev-page">
    <div class="mr-entete"><div><h1>Mes résidences</h1></div><a href="{{ route('gerant.residence.nouvelle') }}" class="btn-pl"><x-ui.icone nom="plus" classe="h-5 w-5" /> Ajouter une résidence</a></div>

    @if(session('message'))
        <div class="pg-confirmation" role="status"><span class="pg-confirmation-icon" aria-hidden="true">✓</span><div><h2>{{ session('message') }}</h2>@if(session('residence_soumise'))<p class="aide">Votre envoi est terminé. Aucun nouvel envoi n’est nécessaire. Retrouvez ci-dessous le suivi du dossier.</p><p class="aide">✓ Envoyée → Examen par Avelis → Publication après accord</p>@endif</div></div>
    @endif
    <div class="gq-cartes">
        @forelse($residences as $r)
            <article class="mr-carte">
                <div class="flex items-center justify-between gap-2">
                    <h2><a href="{{ route('gerant.residence', $r) }}" style="color:inherit;text-decoration:none">{{ $r->nom }}</a></h2>
                    @include('livewire.sejours.partiels.statut-residence', ['r' => $r])
                </div>
                <p class="aide">{{ $r->commune }}@if($r->quartier), {{ $r->quartier }}@endif · {{ $r->unites_count }} logement{{ $r->unites_count > 1 ? 's' : '' }}</p>
                @if($r->soumise_le && $r->statut_verification === 'en_attente')<a href="{{ route('gerant.residence', $r) }}" class="font-semibold text-marque-600">Suivre mon dossier →</a>@endif
                @if($r->statut_verification === 'refusee' && $r->motif_refus)<x-ui.alerte type="erreur">Refusée : {{ $r->motif_refus }}</x-ui.alerte>@endif
                <div class="mr-deux"><a href="{{ route('gerant.residence', $r) }}" class="btn-sec">Modifier</a><a href="{{ route('gerant.residence.acces', $r) }}" class="btn-sec">📍 Accès</a></div>
            </article>
        @empty
            <div class="carte grid place-items-center gap-3 py-10 text-center md:col-span-2">
                <x-ui.icone nom="batiment" classe="h-10 w-10 text-marque-500" />
                <p class="text-lg font-semibold">Vous n'avez pas encore de résidence.</p>
                <a href="{{ route('gerant.residence.nouvelle') }}" class="btn-pl">Ajouter ma résidence</a>
            </div>
        @endforelse
    </div>
</div>
