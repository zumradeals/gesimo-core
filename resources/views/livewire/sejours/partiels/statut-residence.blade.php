@php
    [$texte, $classe] = match (true) {
        $r->statut_verification === 'verifiee' => [$r->aBadgeGestion() ? '✔ Gestion vérifiée' : ($r->estPubliee() ? 'En ligne' : 'Identité à valider'), 'bg-green-100 text-green-800'],
        $r->statut_verification === 'refusee' => ['Refusée', 'bg-red-100 text-red-800'],
        $r->statut_verification === 'suspendue' => ['Suspendue', 'bg-slate-200 text-slate-700'],
        $r->soumise_le !== null => ['En attente de vérification', 'bg-amber-100 text-amber-800'],
        default => ['Brouillon', 'bg-slate-200 text-slate-700'],
    };
@endphp
<span class="inline-block rounded-full px-3 py-0.5 text-[13px] font-bold {{ $classe }}">{{ $texte }}</span>
