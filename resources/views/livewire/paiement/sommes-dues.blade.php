@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div style="display:grid;gap:16px">
    <div class="adm-top"><div><h1>Argent</h1><p class="petit">Ce qu'Avelis vous doit, et ce qui vous a été reversé</p></div></div>
    <div class="kpi">
        <div class="k"><span class="k-i g"><x-ui.icone nom="argent" classe="ic" /></span><b>{{ number_format($aRecevoir, 0, ',', "\u{202F}") }}</b><span>Avelis vous doit (à reverser)</span><small>FCFA</small></div>
        <div class="k"><span class="k-i b"><x-ui.icone nom="carte" classe="ic" /></span><b>{{ number_format($dejaRecu, 0, ',', "\u{202F}") }}</b><span>Déjà reversé</span><small>FCFA</small></div>
        <div class="k"><span class="k-i o"><x-ui.icone nom="balance" classe="ic" /></span><b>{{ number_format($commissions, 0, ',', "\u{202F}") }}</b><span>Commissions retenues</span><small>FCFA</small></div>
        <div class="k"><span class="k-i v"><x-ui.icone nom="porte" classe="ic" /></span><b>{{ number_format($aEncaisser, 0, ',', "\u{202F}") }}</b><span>À encaisser vous-même (arrivées à venir)</span><small>FCFA</small></div>
    </div>

    <section class="carte-a">
        <h2>Séjours</h2>
        <table class="w-full min-w-[640px] border-collapse text-[15px]">
            <thead><tr class="border-b border-trait text-left"><th class="p-2">Séjour</th><th class="p-2 text-right">Encaissé par Avelis</th><th class="p-2 text-right">Commission</th><th class="p-2 text-right">Votre part</th><th class="p-2">État</th></tr></thead>
            <tbody>
            @forelse($lignes as $l)
                <tr class="border-b border-trait">
                    <td class="p-2">{{ $l['r']->nom_client ?? $l['r']->client?->nom ?? 'Client' }} · {{ $l['r']->date_arrivee->translatedFormat('j M') }}<br><span class="aide">{{ $l['r']->unite->nom }}</span></td>
                    <td class="p-2 text-right">{{ number_format($l['encaisse'], 0, ',', ' ') }}</td>
                    <td class="p-2 text-right">{{ $l['commission'] > 0 ? number_format($l['commission'], 0, ',', ' ') : '—' }}</td>
                    <td class="p-2 text-right font-bold">{{ $l['part'] > 0 ? number_format($l['part'], 0, ',', ' ') : '—' }}</td>
                    <td class="p-2">
                        @if($l['rev']?->estPaye())<span class="rounded-full bg-green-100 px-3 py-0.5 text-[13px] font-bold text-green-800">Reversé</span><br><span class="aide">{{ $l['rev']->reference_mobile_money }} · {{ $l['rev']->paye_le->translatedFormat('j M') }}</span>
                        @elseif($l['rev'])<span class="rounded-full bg-amber-100 px-3 py-0.5 text-[13px] font-bold text-amber-800">À reverser</span>
                        @elseif($l['prevue'] !== null)<span class="rounded-full bg-blue-100 px-3 py-0.5 text-[13px] font-bold text-blue-800">Après l'arrivée</span><br><span class="aide">part prévue : {{ number_format($l['prevue'], 0, ',', ' ') }}</span>
                        @else<span class="aide">Remboursé au client</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-center text-gris-doux">Aucun séjour payé pour l'instant.</td></tr>
            @endforelse
            </tbody>
        </table>
        <p class="aide mt-2">Montants en FCFA. Vous ne voyez que vos propres séjours. La commission est retenue par Avelis sur l'argent encaissé.</p>
    </section>
    <x-ui.alerte type="info">Avelis vous reverse votre part <b>après l'arrivée du client</b>, par Mobile Money. Le solde du séjour (hors acompte) vous est payé directement par le client à l'arrivée, sans commission supplémentaire.</x-ui.alerte>
</div>
