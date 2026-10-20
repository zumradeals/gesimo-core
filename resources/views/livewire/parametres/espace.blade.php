@php
    use App\Parametres\Livewire\EspaceParametres as P;
    $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m);
    $onglets = ['accueil' => 'Accueil'] + $rubriques + ['tests' => 'Tests', 'historique' => 'Historique'];
    $aSensible = collect($defs)->contains(fn ($d) => $d['sensible']);
@endphp
<div class="grid gap-4">
    <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Rubriques des réglages">
        @foreach($onglets as $cle => $nom)
            <a href="{{ route('admin.reglages', $cle === 'accueil' ? [] : ['rubrique' => $cle]) }}" class="puce shrink-0 {{ $rubrique === $cle ? 'puce-active' : '' }}">{{ $nom }}</a>
        @endforeach
    </div>

    @if($message)<x-ui.alerte type="succes">{{ $message }}</x-ui.alerte>@endif

    {{-- ====================================================== ACCUEIL --}}
    @if($rubrique === 'accueil')
        <div class="carte"><h1 class="text-xl font-extrabold">Réglages de la plateforme</h1><p class="aide">Tout se règle ici, sans toucher à aucun fichier technique. Chaque changement est tracé.</p></div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($rubriques as $cle => $nom)
                <a href="{{ route('admin.reglages', ['rubrique' => $cle]) }}" class="carte grid gap-1 hover:border-marque-600">
                    <h2 class="font-extrabold">{{ $nom }}</h2><p class="aide">{{ $etats[$cle]['texte'] }}</p>
                    <span class="w-fit rounded-full px-3 py-0.5 text-[13px] font-bold {{ $etats[$cle]['ton'] === 'b-conf' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $etats[$cle]['etat'] }}</span>
                </a>
            @endforeach
        </div>
        <div class="grid gap-3 sm:grid-cols-2 md:max-w-2xl">
            <a href="{{ route('admin.reglages', ['rubrique' => 'tests']) }}" class="btn btn-second btn-bloc">Tester les connexions</a>
            <a href="{{ route('admin.reglages', ['rubrique' => 'historique']) }}" class="btn btn-second btn-bloc">Historique des modifications</a>
        </div>

    {{-- ====================================================== TESTS --}}
    @elseif($rubrique === 'tests')
        <div class="carte"><h1 class="text-xl font-extrabold">Tester les connexions</h1><p class="aide">Chaque test est sans danger : aucun vrai paiement, aucune donnée modifiée. Un résultat ne montre jamais de clé ni de mot de passe.</p></div>
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="carte grid gap-2"><h2 class="font-extrabold">✉ E-mail</h2><p class="aide">Envoie un message de test à votre adresse ({{ auth()->user()->email }}).</p>
                <button wire:click="testerEmail" wire:loading.attr="disabled" class="btn btn-bloc">Lancer le test e-mail</button>
                @include('livewire.parametres.resultat', ['r' => $resultats['email'] ?? null])</div>
            <div class="carte grid gap-2"><h2 class="font-extrabold">💳 Paiement (GeniusPay)</h2><p class="aide">Demande à GeniusPay le solde du compte pour vérifier que les clés sont acceptées (lecture seule).</p>
                <button wire:click="testerPaiement" wire:loading.attr="disabled" class="btn btn-bloc">Lancer le test paiement</button>
                @include('livewire.parametres.resultat', ['r' => $resultats['paiement'] ?? null])
                <x-ui.alerte type="attention">Le secret du webhook est vérifié seulement quand GeniusPay envoie un vrai message.</x-ui.alerte></div>
        </div>

    {{-- ====================================================== HISTORIQUE --}}
    @elseif($rubrique === 'historique')
        <div class="carte"><h1 class="text-xl font-extrabold">Historique des modifications</h1><p class="aide">Lecture seule : l'historique ne peut ni se modifier ni se supprimer (verrouillé dans la base de données). Pour un secret, seul le fait qu'il a changé est gardé.</p></div>
        <div class="flex flex-wrap gap-2">@foreach(['' => 'Tout', 'paiement' => 'Paiement', 'securite' => 'Sécurité', 'reservation' => 'Réservation'] as $k => $n)<button wire:click="$set('filtreHistorique', '{{ $k }}')" class="puce {{ $filtreHistorique === $k ? 'puce-active' : '' }}">{{ $n }}</button>@endforeach</div>
        <div class="carte overflow-x-auto p-0 lg:p-0">
            <table class="w-full min-w-[640px] text-left text-[15px]">
                <thead><tr class="border-b border-trait text-gris-doux"><th class="p-3">Quand</th><th class="p-3">Qui</th><th class="p-3">Réglage</th><th class="p-3">Avant</th><th class="p-3">Après</th></tr></thead>
                <tbody>
                @forelse($historique as $h)
                    <tr class="border-b border-trait last:border-0 align-top"><td class="whitespace-nowrap p-3">{{ $h->created_at->translatedFormat('j M H:i') }}</td><td class="p-3">{{ $h->auteur?->nom ?? 'Système' }}</td><td class="p-3">{{ $h->parametre->cle }}@if($h->est_secret) 🔒@endif</td>
                    @if($h->est_secret)<td class="p-3 italic text-gris-doux" colspan="2">secret modifié (valeur jamais conservée)</td>@else<td class="p-3">{{ \Illuminate\Support\Str::limit((string) $h->ancienne_valeur, 60) }}</td><td class="p-3">{{ \Illuminate\Support\Str::limit((string) $h->nouvelle_valeur, 60) }}</td>@endif</tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-gris-doux">Aucun changement enregistré.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $historique->links() }}

    {{-- ====================================================== RUBRIQUES --}}
    @else
        <form wire:submit="enregistrer" class="carte grid gap-4 xl:max-w-4xl">
            <h1 class="text-xl font-extrabold">{{ $rubriques[$rubrique] }}</h1>
            @if($rubrique === 'reservation')
                <x-ui.alerte type="info">Un changement s'applique <b>aux nouvelles réservations seulement</b>. Les réservations existantes gardent les conditions du jour où elles ont été créées.</x-ui.alerte>
            @endif
            @if($rubrique === 'paiement')
                <x-ui.alerte type="attention">Commencez en <b>mode test</b> : aucun vrai argent n'est débité. Adresse à donner à GeniusPay pour les webhooks : <b class="break-all">{{ $webhookUrl }}</b></x-ui.alerte>
            @endif
            @if(isset($erreurs['mot_de_passe']) && ! $aSensible)<x-ui.alerte type="erreur">{{ $erreurs['mot_de_passe'] }}</x-ui.alerte>@endif

            @foreach($defs as $cle => $def)
                @php $k = P::cle($cle); @endphp
                <div class="champ">
                    @if($def['type'] !== 'offres')<label class="etiquette" for="f_{{ $k }}">{{ $def['label'] }}@if($def['type'] === 'secret') 🔒@endif</label>@endif
                    @switch($def['type'])
                        @case('long')<textarea id="f_{{ $k }}" wire:model="valeurs.{{ $k }}" rows="5" class="saisie py-2"></textarea>@break
                        @case('choix')<select id="f_{{ $k }}" wire:model="valeurs.{{ $k }}" class="saisie">@foreach($def['choix'] as $v => $n)<option value="{{ $v }}">{{ $n }}</option>@endforeach</select>@break
                        @case('entier')@case('decimal')<input id="f_{{ $k }}" wire:model="valeurs.{{ $k }}" inputmode="{{ $def['type'] === 'entier' ? 'numeric' : 'decimal' }}" class="saisie">@break
                        @case('heure')<input id="f_{{ $k }}" wire:model="valeurs.{{ $k }}" placeholder="14:00" class="saisie">@break
                        @case('logo')
                            <div class="flex items-center gap-3">@if($logoUrl)<img src="{{ $logoUrl }}" alt="Logo actuel" class="h-16 w-auto rounded border border-trait">@else<div class="flex h-16 w-16 items-center justify-center rounded border border-trait text-gris-doux">Logo</div>@endif
                            <input id="f_{{ $k }}" type="file" wire:model="logo" accept="image/png,image/jpeg" class="text-[15px]"></div>@error('logo')<p class="erreur-champ">{{ $message }}</p>@enderror
                            @break
                        @case('secret')
                            @if($secretsEnregistres[$cle] && empty($remplacer[$cle]))
                                <div class="flex gap-2"><input value="••••••••••••" disabled class="saisie"><button type="button" wire:click="remplacer('{{ $cle }}')" class="btn btn-second sm:w-auto sm:min-w-0 px-4">Remplacer</button></div>
                                <span class="aide">Enregistré. La valeur n'est jamais réaffichée.</span>
                            @else
                                <input id="f_{{ $k }}" type="password" autocomplete="off" wire:model="secrets.{{ $k }}" class="saisie" placeholder="{{ $secretsEnregistres[$cle] ? 'Nouvelle valeur' : 'Pas encore saisi' }}">
                            @endif
                            @break
                        @case('offres')
                            <p class="etiquette">Offres proposées aux gérants</p>
                            <div class="grid gap-2">
                                @foreach($valeurs[$k] ?? [] as $i => $o)
                                    <div class="grid grid-cols-2 gap-2 rounded-xl border border-trait p-2 sm:grid-cols-[1fr_110px_150px_auto]">
                                        <input wire:model="valeurs.{{ $k }}.{{ $i }}.nom" class="saisie col-span-2 sm:col-span-1" placeholder="Nom (ex. Une semaine)" aria-label="Nom de l'offre {{ $i + 1 }}">
                                        <input wire:model="valeurs.{{ $k }}.{{ $i }}.jours" inputmode="numeric" class="saisie" placeholder="Jours" aria-label="Durée en jours">
                                        <input wire:model="valeurs.{{ $k }}.{{ $i }}.prix" inputmode="numeric" class="saisie" placeholder="Prix FCFA" aria-label="Prix en FCFA">
                                        <button type="button" wire:click="retirerOffre({{ $i }})" class="col-span-2 min-h-[44px] font-semibold text-red-700 sm:col-span-1">Retirer</button>
                                    </div>
                                @endforeach
                            </div>
                            @if(count($valeurs[$k] ?? []) < 4)<button type="button" wire:click="ajouterOffre" class="btn btn-second sm:w-auto">+ Ajouter une offre</button>@endif
                            @break
                        @default<input id="f_{{ $k }}" wire:model="valeurs.{{ $k }}" class="saisie" autocomplete="off">
                    @endswitch
                    @if($def['aide'])<span class="aide">{{ $def['aide'] }}</span>@endif
                    @if(isset($erreurs[$cle]))<p class="erreur-champ" role="alert">{{ $erreurs[$cle] }}</p>@endif
                </div>
            @endforeach

            @if($rubrique === 'paiement')
                <div class="rounded-xl bg-marque-50 p-3 text-[15px]">Plancher d'acompte calculé : <b>{{ $plancher !== null ? $plancher.' %' : 'inconnu (réglez la commission et les frais)' }}</b> (commission + frais, arrondi au-dessus).</div>
                @if($residencesSousPlancher > 0)<x-ui.alerte type="attention">{{ $residencesSousPlancher }} résidence(s) ont un acompte sous ce plancher : leurs nouvelles réservations utiliseront le plancher.</x-ui.alerte>@endif
                <div><button type="button" wire:click="testerPaiement" class="btn btn-second btn-bloc sm:w-auto">Tester la connexion</button></div>
                @include('livewire.parametres.resultat', ['r' => $resultats['paiement'] ?? null])
            @endif
            @if($rubrique === 'notifications')
                <div><button type="button" wire:click="testerEmail" class="btn btn-second btn-bloc sm:w-auto">Tester la connexion</button></div>
                @include('livewire.parametres.resultat', ['r' => $resultats['email'] ?? null])
                <a href="{{ route('admin.messages') }}" class="btn btn-second btn-bloc sm:w-auto">Modifier les modèles de messages</a>
            @endif

            @if($aSensible)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="font-bold text-amber-800">Réglage sensible : retapez votre mot de passe pour enregistrer.</p>
                    <div class="champ mt-2"><label class="etiquette" for="mdp">Votre mot de passe</label><input id="mdp" type="password" autocomplete="current-password" wire:model="motDePasse" class="saisie">
                        @if(isset($erreurs['mot_de_passe']))<p class="erreur-champ" role="alert">{{ $erreurs['mot_de_passe'] }}</p>@endif</div>
                </div>
            @endif
            <button class="btn btn-bloc md:w-auto" wire:loading.attr="disabled">Enregistrer</button>
        </form>
    @endif
</div>
