@php
    use App\Sejours\Services\EtatDocument as E;
    $etatIdentite = E::de($identiteDernier, $profil->identiteValidee());
    $baux = $residences->mapWithKeys(function ($r) {
        $dernier = $r->documents->where('type', 'justificatif_droit_gestion')->whereNull('remplace_le')->sortByDesc('id')->first();

        return [$r->id => ['dernier' => $dernier, 'etat' => E::de($dernier, $r->aBadgeGestion())]];
    });
    $toutValide = $etatIdentite === E::VALIDE && $residences->isNotEmpty() && $baux->every(fn ($b) => $b['etat'] === E::VALIDE);
    $dejaPubliees = $residences->filter(fn ($r) => $r->statut_verification === 'verifiee' && $baux[$r->id]['etat'] !== E::VALIDE);
@endphp
<div class="mr-page ev-page">
    <div class="mr-entete"><div><h1>Ma vérification</h1><p class="petit">Deux documents suffisent pour publier vos résidences.</p></div>@if($verifie)<span class="mr-badge gq-verifie">✓ Gérant vérifié</span>@endif</div>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($toutValide)
        <x-ui.alerte type="succes">Votre dossier est validé : vous pouvez publier vos résidences. Le badge « Gérant vérifié » apparaît sur votre profil public.</x-ui.alerte>
    @elseif($dejaPubliees->isNotEmpty() && $etatIdentite === E::VALIDE)
        <x-ui.alerte type="attention">{{ $dejaPubliees->count() === 1 ? 'Votre résidence « '.$dejaPubliees->first()->nom.' » est déjà publiée et le reste.' : 'Vos résidences déjà publiées le restent.' }} Pour obtenir le badge « Gérant vérifié », ajoutez le contrat de bail.</x-ui.alerte>
    @else
        <x-ui.alerte type="info"><b>Pour publier :</b> votre pièce d’identité et le contrat de bail (ou justificatif de gestion) de votre résidence. L’équipe Avelis les contrôle sous quelques jours. Rien d’autre n’est demandé.</x-ui.alerte>
    @endif
    <div class="ev-cols">
        <div class="gq-pieces">
            <section class="gq-piece {{ E::classe($etatIdentite) }}" id="piece-identite-gerant">
                <div class="gq-tete"><h2>1 · Ma pièce d’identité <small class="petit">(une seule fois pour toutes mes résidences)</small></h2><span class="mr-badge gq-b-{{ E::classe($etatIdentite) }}">{{ E::libelle($etatIdentite) }}</span></div>
                @if($etatIdentite === E::VALIDE)<p class="petit">Validée par Avelis.</p>
                @elseif($etatIdentite === E::CONTROLE)<p class="petit">Reçue le {{ $identiteDernier?->created_at?->translatedFormat('j M') }}. Réponse sous quelques jours.</p>
                @elseif($etatIdentite === E::REFUSE)<p class="gq-motif">Motif : {{ $identiteDernier->motif_refus }}</p>
                @else<p class="petit">Nécessaire pour publier.</p>@endif
                @if(in_array($etatIdentite, [E::A_FOURNIR, E::REFUSE], true))
                    <form wire:submit="deposerIdentite" class="gq-depot">
                        <b>Photo ou PDF · 5 Mo au maximum</b>
                        <input type="file" wire:key="identite-{{ $version }}" wire:model="identite" accept="image/*,application/pdf" class="saisie py-3" aria-label="Pièce d’identité">
                        @error('identite')<p class="erreur-champ">{{ $message }}</p>@enderror
                        <div wire:loading wire:target="identite" class="petit">Chargement du fichier…</div>
                        <button class="btn-pl" wire:loading.attr="disabled" wire:target="deposerIdentite">Envoyer ma pièce d’identité</button>
                    </form>
                @endif
            </section>
            @forelse($residences as $r)
                @php $b = $baux[$r->id]; $e = $b['etat']; @endphp
                <section class="gq-piece {{ E::classe($e) }}" wire:key="bail-{{ $r->id }}">
                    <div class="gq-tete"><h2>2 · Contrat de bail de {{ $r->nom }}</h2><span class="mr-badge gq-b-{{ E::classe($e) }}">{{ E::libelle($e) }}</span></div>
                    @if($e === E::VALIDE)<p class="petit">Validé par Avelis.</p>
                    @elseif($e === E::CONTROLE)<p class="petit">Reçu le {{ $b['dernier']?->created_at?->translatedFormat('j M') }}. Réponse sous quelques jours.</p>
                    @elseif($e === E::REFUSE)<p class="gq-motif">Motif : {{ $b['dernier']->motif_refus }}</p>
                    @else<p class="petit">{{ $r->statut_verification === 'verifiee' ? 'Demandé pour obtenir le badge « Gérant vérifié ».' : 'Nécessaire pour publier.' }}</p>@endif
                    @if(in_array($e, [E::A_FOURNIR, E::REFUSE], true))
                        <form wire:submit="deposerBail({{ $r->id }})" class="gq-depot">
                            <b>Photo ou PDF · 5 Mo au maximum</b>
                            <p class="petit" style="margin:0">Le contrat de bail, ou tout document qui prouve que vous gérez cette résidence (titre de propriété, mandat de gestion).</p>
                            <input type="file" wire:key="bail-{{ $r->id }}-{{ $version }}" wire:model="baux.{{ $r->id }}" accept="image/*,application/pdf" class="saisie py-3" aria-label="Contrat de bail de {{ $r->nom }}">
                            @error("baux.{$r->id}")<p class="erreur-champ">{{ $message }}</p>@enderror
                            <div wire:loading wire:target="baux.{{ $r->id }}" class="petit">Chargement du fichier…</div>
                            <button class="btn-pl" wire:loading.attr="disabled" wire:target="deposerBail">Envoyer le contrat de bail</button>
                        </form>
                    @endif
                </section>
            @empty
                <section class="gq-piece att"><div class="gq-tete"><h2>2 · Contrat de bail de votre résidence</h2></div><p class="petit">Ajoutez d’abord une résidence : vous y joindrez son contrat de bail.</p><a href="{{ route('gerant.residence.nouvelle') }}" class="btn-pl">Ajouter ma résidence</a></section>
            @endforelse
            <p class="petit">Si vous gérez plusieurs résidences : l’identité se dépose une fois ; le bail se dépose pour chaque résidence.</p>
        </div>
        <aside class="mr-col">
            <section class="mr-carte rv-ivoire"><h2>Et ensuite ?</h2><ul class="rv-liste"><li>Vous déposez les deux documents</li><li>L’équipe Avelis les contrôle (sous quelques jours)</li><li>Vous êtes prévenu par e-mail</li><li>Vous publiez, et le badge « Gérant vérifié » apparaît</li></ul></section>
            <section class="mr-carte"><h2>Qui voit mes documents ?</h2><p class="petit" style="margin:0">Seule l’équipe Avelis autorisée. Jamais le public ni les voyageurs. Chaque consultation est enregistrée.</p></section>
        </aside>
    </div>
</div>
