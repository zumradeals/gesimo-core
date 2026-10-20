<div class="grid gap-4">
<div class="rounded-2xl bg-gradient-to-br from-marque-600 to-marque-500 p-5 text-white lg:p-8"><h1 class="text-2xl font-extrabold lg:text-3xl">Gérez vos résidences avec GESIMO</h1><p class="mt-1 opacity-95">Plus de réservations, paiements sécurisés, reversement après l'arrivée du client.</p></div>
<div class="grid gap-3 md:grid-cols-3">
<div class="carte"><h2 class="font-extrabold">1. Créez votre compte</h2><p class="aide">Téléphone et e-mail, en 2 minutes.</p></div>
<div class="carte"><h2 class="font-extrabold">2. Ajoutez votre résidence</h2><p class="aide">Photos, prix, règlement et pièces.</p></div>
<div class="carte"><h2 class="font-extrabold">3. Nous vérifions</h2><p class="aide">Puis vos logements apparaissent et reçoivent des réservations.</p></div></div>
<div class="carte md:max-w-3xl"><h2 class="font-extrabold">Combien ça coûte ?</h2>
@php $taux = \App\Paiement\Services\ReglagesPaiement::commissionPourcentage(); @endphp
<p class="aide">GESIMO retient une commission sur chaque réservation payée en ligne @if($taux !== null)({{ rtrim(rtrim(number_format($taux, 2, ',', ''), '0'), ',') }} %)@endif. Les réservations prises par téléphone sont gratuites.</p></div>
<a href="{{ route('inscription', ['profil' => 'gerant']) }}" class="btn md:w-auto">Créer mon compte gérant</a></div>
