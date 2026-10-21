<!DOCTYPE html>
<html lang="fr"><body style="font-family:Arial,sans-serif;color:#1b2430;line-height:1.5">
<p>Bonjour {{ $nom }},</p>
<p>Voici votre lien pour vous connecter à Avelis Séjours. Il ne marche qu'une seule fois, pendant {{ $minutes }} minutes.</p>
<p><a href="{{ $lien }}" style="display:inline-block;background:#0f766e;color:#fff;padding:14px 22px;border-radius:10px;text-decoration:none;font-weight:bold">Me connecter</a></p>
<p style="color:#5b6675;font-size:14px">Si vous n'avez rien demandé, ignorez ce message : personne ne peut entrer sans ce lien.</p>
</body></html>
