<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FF6A00">
    <title>Hors connexion — {{ $nom }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#fff8f0;color:#17243b;line-height:1.5}
        header{background:#17243b;color:#fff;padding:14px 16px;font-weight:800;font-size:18px}
        main{max-width:520px;margin:0 auto;padding:28px 16px;text-align:center}
        .rond{width:72px;height:72px;margin:0 auto 12px;border-radius:50%;background:#ffeadb;color:#b94700;font-size:34px;display:flex;align-items:center;justify-content:center}
        h1{font-size:22px;margin:0 0 8px}p{margin:0 0 14px}.aide{font-size:14px;color:#526077}
        .btn{display:flex;align-items:center;justify-content:center;min-height:54px;width:100%;border-radius:12px;border:2px solid #17243b;background:#ff6a00;color:#17243b;font:700 18px inherit;font-family:inherit;margin:0 0 10px;text-decoration:none;cursor:pointer}
        .btn.second{background:#fff;color:#17243b}[hidden]{display:none}
    </style>
</head>
<body>
<header>{{ $nom }}</header>
<main>
    <div class="rond" aria-hidden="true">📡</div>
    <h1>Vous êtes hors connexion</h1>
    <p>Cette page n'a pas encore été enregistrée sur votre téléphone. Dès que le réseau revient, elle s'ouvrira.</p>
    <button class="btn" type="button" onclick="location.reload()">Réessayer</button>
    <a class="btn second" id="mes-reservations" href="/mes-reservations" hidden>Voir mes réservations enregistrées</a>
    <p class="aide">Vos données ne sont pas perdues. Rien n'a été envoyé.</p>
</main>
<script>
    // Le lien vers « Mes réservations » n'apparaît que si une copie est enregistrée sur ce téléphone.
    if ('caches' in window) { caches.match('/mes-reservations', { ignoreVary: true }).then(function (r) { if (r) { document.getElementById('mes-reservations').hidden = false; } }); }
    window.addEventListener('online', function () { location.reload(); });
</script>
</body>
</html>
