<div class="grid gap-4">
    <h1 class="text-2xl font-extrabold">Mon personnel</h1>
    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif
    @if($erreur)<x-ui.alerte type="erreur">{{ $erreur }}</x-ui.alerte>@endif

    @if($residences->isEmpty())
        <x-ui.alerte type="attention">Ajoutez d'abord une résidence avant d'inviter du personnel.</x-ui.alerte>
    @else
    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        <form wire:submit="inviter" class="carte grid gap-3">
            <h2 class="text-lg font-extrabold">Inviter une personne</h2>
            <div class="champ"><label class="etiquette" for="residence">Résidence</label>
                <select id="residence" wire:model="residenceId" class="saisie">@foreach($residences as $r)<option value="{{ $r->id }}">{{ $r->nom }}</option>@endforeach</select></div>
            <div class="champ"><label class="etiquette" for="nom">Nom</label><input id="nom" wire:model="nom" class="saisie" placeholder="Ex. Yao (gardien)">@error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="email">E-mail</label><input id="email" type="email" wire:model="email" class="saisie">@error('email')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ"><label class="etiquette" for="tel">Téléphone</label><input id="tel" type="tel" inputmode="tel" wire:model="telephone" class="saisie">@error('telephone')<p class="erreur-champ">{{ $message }}</p>@enderror</div>
            <div class="champ">
                <span class="etiquette">Ce que cette personne peut faire</span>
                <div class="flex flex-wrap gap-2">
                    <label class="puce {{ $droitCheckin ? 'puce-active' : '' }}"><input type="checkbox" class="sr-only" wire:model.live="droitCheckin">Arrivées et départs</label>
                    <label class="puce {{ $droitCalendrier ? 'puce-active' : '' }}"><input type="checkbox" class="sr-only" wire:model.live="droitCalendrier">Calendrier</label>
                </div>
            </div>
            <button class="btn btn-bloc" wire:loading.attr="disabled">Envoyer l'invitation</button>
            <p class="aide">La personne reçoit un lien par e-mail pour créer son accès. Elle ne voit que cette résidence.</p>
        </form>

        <div class="carte grid gap-2">
            <h2 class="text-lg font-extrabold">Mon personnel</h2>
            @foreach($membres as $m)
                <div class="flex items-start justify-between gap-2 border-b border-dashed border-trait pb-2">
                    <div><p class="font-bold">{{ $m->utilisateur->nom }} <span class="rounded-full bg-green-100 px-2 py-0.5 text-[12px] text-green-800">Actif</span></p>
                        <p class="aide">{{ $m->residence->nom }} · {{ collect([$m->peut_gerer_calendrier ? 'calendrier' : null, $m->peut_gerer_checkin_checkout ? 'arrivées/départs' : null])->filter()->implode(', ') }}</p></div>
                    <button type="button" wire:click="retirer({{ $m->id }})" wire:confirm="Retirer l'accès de {{ $m->utilisateur->nom }} ?" class="text-sm font-semibold text-red-700">Retirer l'accès</button>
                </div>
            @endforeach
            @foreach($invitations as $i)
                <div class="flex items-start justify-between gap-2 border-b border-dashed border-trait pb-2">
                    <div><p class="font-bold">{{ $i->nom }} <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[12px] text-amber-800">Invitation envoyée</span></p><p class="aide">{{ $i->residence->nom }} · {{ $i->email }}</p></div>
                    <button type="button" wire:click="annulerInvitation({{ $i->id }})" class="text-sm font-semibold text-red-700">Annuler</button>
                </div>
            @endforeach
            @if($membres->isEmpty() && $invitations->isEmpty())<p class="aide">Personne pour l'instant.</p>@endif
        </div>
    </div>
    @endif
</div>
