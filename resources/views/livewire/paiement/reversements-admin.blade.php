@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); @endphp
<div class="grid gap-4">
    <div class="adm-top"><div><h1>Reversements</h1></div></div>
    <x-ui.alerte type="info">GESIMO ne verse rien automatiquement : vous envoyez l'argent par Mobile Money <b>en dehors de la plateforme</b>, puis vous l'enregistrez ici (référence et date).</x-ui.alerte>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif

    <div class="grid gap-4 lg:grid-cols-[380px_1fr] lg:items-start">
        <div class="carte-p">
            <h2>À reverser ({{ $groupes->count() }} gérant{{ $groupes->count() > 1 ? 's' : '' }})</h2>
            @forelse($groupes as $id => $lignes)
                <button type="button" wire:click="$set('gerantId', {{ $id }})" class="flex items-center justify-between rounded-xl border p-3 text-left {{ $gerantId === $id ? 'border-marque-600 bg-marque-50' : 'border-trait' }}">
                    <span><b>{{ $lignes->first()->gerant->nom }}</b><br><span class="aide">{{ $lignes->count() }} séjour{{ $lignes->count() > 1 ? 's' : '' }} · {{ \App\Noyau\Services\Telephone::afficher($lignes->first()->gerant->telephone) }}</span></span>
                    <b>{{ number_format($lignes->sum('montant'), 0, ',', ' ') }}</b>
                </button>
            @empty
                <p class="aide">Rien à reverser pour le moment.</p>
            @endforelse
            @if($groupes->isNotEmpty())<div class="flex justify-between border-t border-trait pt-2"><b>Total</b><b>{{ $fr((int) $groupes->flatten()->sum('montant')) }}</b></div>@endif
        </div>

        @if($choisi->isNotEmpty())
        <form wire:submit="enregistrer" class="carte-p">
            <h2>Reverser à {{ $gerant->nom }}</h2>
            <p class="aide">Numéro Mobile Money du gérant : {{ \App\Noyau\Services\Telephone::afficher($gerant->telephone) }}</p>
            <table class="w-full text-[15px]"><thead><tr class="border-b border-trait text-left"><th class="p-1">Séjour</th><th class="p-1 text-right">Part</th></tr></thead><tbody>
                @foreach($choisi as $l)<tr class="border-b border-dashed border-trait"><td class="p-1">{{ $l->reservation->nom_client ?? 'Réservation' }} #{{ $l->reservation_id }} · {{ $l->reservation->date_arrivee->translatedFormat('j M') }}<br><span class="aide">{{ $l->reservation->unite->nom }}</span></td><td class="p-1 text-right">{{ number_format($l->montant, 0, ',', ' ') }}</td></tr>@endforeach
            </tbody></table>
            <div class="flex justify-between text-lg"><b>Montant à envoyer</b><b>{{ $fr((int) $choisi->sum('montant')) }}</b></div>
            <div class="champ"><label class="etiquette" for="ref">Référence de l'envoi Mobile Money</label><input id="ref" wire:model="reference" class="saisie" placeholder="Ex. MM-48213"></div>
            <div class="champ"><label class="etiquette" for="date">Date de l'envoi</label><input id="date" type="date" wire:model="date" max="{{ now()->toDateString() }}" class="saisie">@error('date')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif
            <button class="btn-pl pleine" wire:loading.attr="disabled" wire:confirm="Avez-vous bien envoyé {{ $fr((int) $choisi->sum('montant')) }} à {{ $gerant->nom }} ?">J'ai envoyé l'argent : enregistrer</button>
            <p class="aide">Une fois enregistré, il ne se modifie plus (écriture comptable). Une erreur se corrige par une nouvelle écriture.</p>
        </form>
        @endif
    </div>

    <div class="carte overflow-x-auto"><h2 class="mb-2 text-lg font-extrabold">Historique des reversements</h2>
        <table class="w-full min-w-[520px] text-[15px]"><thead><tr class="border-b border-trait text-left"><th class="p-2">Date</th><th class="p-2">Gérant</th><th class="p-2">Référence</th><th class="p-2 text-right">Montant</th></tr></thead><tbody>
        @forelse($historique as $h)<tr class="border-b border-trait"><td class="p-2">{{ $h->paye_le->translatedFormat('j M Y') }}</td><td class="p-2">{{ $h->gerant->nom }}</td><td class="p-2">{{ $h->reference_mobile_money }}</td><td class="p-2 text-right">{{ number_format($h->montant, 0, ',', ' ') }}</td></tr>@empty<tr><td colspan="4" class="p-3 text-center text-gris-doux">Aucun reversement enregistré.</td></tr>@endforelse
        </tbody></table></div>
</div>
