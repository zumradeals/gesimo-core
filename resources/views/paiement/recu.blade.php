@php $fr = fn (int $m) => \App\Noyau\Services\Format::fcfa($m); $u = $r->unite; $legale = \App\Parametres\Services\Marque::ligneLegale(); @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu {{ $paiement->numeroRecu() }} — Avelis Séjours</title>
    <style>
        *{box-sizing:border-box}
        body.charte{margin:0;background:#e9ecf1;color:#17243b;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;padding:24px 12px}
        .recu{background:#fff;max-width:720px;margin:0 auto;border-radius:6px;box-shadow:0 8px 30px #17243b22;padding:30px;display:grid;gap:16px}
        .tete{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;border-bottom:3px solid #ff6a00;padding-bottom:14px}
        .logo{display:flex;align-items:center;gap:9px;font-weight:800;font-size:20px}
        .logo i{display:grid;place-items:center;width:34px;height:34px;border-radius:11px;background:#ff6a00;color:#17243b;font-style:normal;font-size:22px}
        .petit{font-size:13px;color:#526077;margin:0}
        .ligne{display:flex;justify-content:space-between;gap:12px;padding:5px 0;font-size:15px}
        .ligne dt,.ligne dd{margin:0}.ligne dt{color:#526077}.ligne dd{text-align:right}
        dl{margin:0}
        .sejour{border-top:1px solid #e4e7ed;border-bottom:1px solid #e4e7ed;padding:12px 0}
        .montant{background:#fff8f0;border:1px solid #ffeadb;border-radius:10px;padding:14px 16px;display:flex;justify-content:space-between;gap:12px;align-items:center;font-weight:800;font-size:20px}
        .actions{display:flex;gap:10px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:10px 18px;border-radius:10px;background:#ff6a00;color:#17243b;font:inherit;font-weight:800;border:0;cursor:pointer;text-decoration:none}
        .btn.sec{background:#fff;border:2px solid #17243b}
        .legale{border-top:1px solid #e4e7ed;padding-top:10px}
        @media print{body.charte{background:#fff;padding:0}.recu{box-shadow:none;border-radius:0;max-width:none}.actions{display:none}@page{margin:14mm}}
    </style>
</head>
<body class="charte">
<main class="recu">
    <div class="tete">
        <div><div class="logo"><i>A</i>Avelis Séjours</div><p class="petit" style="margin-top:6px">Reçu de paiement</p></div>
        <p style="text-align:right;font-size:14px;margin:0"><b>{{ $paiement->numeroRecu() }}</b><br>{{ $paiement->updated_at->translatedFormat('j F Y, H \h i') }}</p>
    </div>
    <dl>
        <div class="ligne"><dt>Client</dt><dd>{{ $r->client->nom }} · {{ \App\Noyau\Services\Telephone::afficher($r->client->telephone) }}</dd></div>
        <div class="ligne"><dt>Réservation</dt><dd>n° {{ $r->id }}</dd></div>
        <div class="ligne"><dt>Référence de paiement</dt><dd>{{ $paiement->reference_geniuspay }}</dd></div>
    </dl>
    <div class="sejour">
        <b>{{ $u->nom }} — {{ $u->residence->nom }} ({{ $u->residence->commune }})</b>
        <div class="ligne"><span>Du {{ $r->date_arrivee->translatedFormat('j M') }} au {{ $r->date_depart->translatedFormat('j M Y') }} ({{ $r->nombre_nuits ?? $r->date_arrivee->diffInDays($r->date_depart) }} nuits)</span><span>{{ $fr($r->montant_total) }}</span></div>
    </div>
    <div class="montant"><span>{{ $r->solde > 0 ? 'Acompte payé en ligne' : 'Total payé en ligne' }}</span><span>{{ $fr($paiement->montant) }}</span></div>
    @if($r->solde > 0)<div class="ligne"><span>Reste à régler au gérant à l'arrivée</span><b>{{ $fr($r->solde) }}</b></div>@endif
    <p class="petit">Paiement encaissé par Avelis via GeniusPay. Avelis est une plateforme de mise en relation ; le solde éventuel est réglé directement au gérant.</p>
    @if($legale)<p class="petit legale">{{ $legale }}</p>@endif
    <div class="actions">
        <button onclick="window.print()" class="btn">Télécharger / imprimer (PDF)</button>
        <a href="{{ route('mes-reservations') }}" class="btn sec">Retour</a>
    </div>
</main>
</body>
</html>
