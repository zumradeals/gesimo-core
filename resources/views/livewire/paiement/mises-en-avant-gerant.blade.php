@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="grid gap-4">
    <div class="adm-top"><div><h1>Mes mises en avant</h1></div></div>
    <a href="{{ route('gerant.mise-en-avant.acheter') }}" class="btn-pl">+ Nouvelle mise en avant</a>
    @foreach($enAttente as $m)
        <x-ui.alerte type="attention">Achat « {{ $m->offre }} » pour {{ $m->residence->nom }} en attente de paiement. <a class="font-bold underline" href="{{ route('gerant.mise-en-avant.retour', $m) }}">Vérifier le paiement</a></x-ui.alerte>
    @endforeach
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($mises as $m)
            @php $etat = $m->etat(); @endphp
            <article class="carte-p">
                <div class="flex items-center justify-between gap-2"><b>{{ $m->residence->nom }}</b>
                    <span class="rounded-full px-3 py-0.5 text-[13px] font-bold {{ match ($etat) { 'En cours' => 'bg-green-100 text-green-800', 'Programmée' => 'bg-blue-100 text-blue-800', 'Suspendue par GESIMO' => 'bg-amber-100 text-amber-800', default => 'bg-slate-200 text-slate-700' } }}">{{ $etat }}</span></div>
                <p class="aide">Du {{ $m->date_debut->translatedFormat('j M') }} au {{ $m->date_fin->translatedFormat('j M Y') }} · {{ $m->offre }} · {{ $fr($m->prix) }}</p>
                @if($etat === 'En cours')<p class="aide">Annonce « Sponsorisé » en tête des recherches jusqu'au {{ $m->date_fin->translatedFormat('j M') }}.</p>@endif
                @if($m->statut === 'suspendue')<x-ui.alerte type="attention">Votre annonce n'est plus mise en avant. Contactez l'équipe pour plus d'informations.</x-ui.alerte>@endif
            </article>
        @empty
            <div class="carte md:col-span-2"><p class="font-semibold">Vous n'avez pas encore mis votre résidence en avant.</p><p class="aide">Une mise en avant place votre annonce en tête des recherches avec la mention « Sponsorisé ».</p></div>
        @endforelse
    </div>
</div>
