{{-- Gabarit commun des e-mails (contrat docs/contrats/espace-voyageur-v3.md) : styles écrits dans le message pour les messageries. --}}
@php $legale = \App\Parametres\Services\Marque::ligneLegale(); @endphp
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $titre }}</title></head>
<body style="margin:0;padding:0;background:#eef0f4">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f4;padding:18px 8px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#17243b;line-height:1.55;border-radius:10px;overflow:hidden">
<tr><td style="background:#17243b;padding:18px 24px"><span style="display:inline-block;width:34px;height:34px;line-height:34px;text-align:center;border-radius:10px;background:#ff6a00;color:#17243b;font-weight:bold;font-size:20px;vertical-align:middle">A</span> <span style="color:#ffffff;font-weight:bold;font-size:18px;vertical-align:middle;margin-left:6px">Avelis Séjours</span></td></tr>
<tr><td style="padding:28px 24px 8px"><h1 style="margin:0 0 14px;font-size:24px;line-height:1.25;color:#17243b">{{ $titre }}</h1><div style="font-size:16px">{{ $slot }}</div></td></tr>
@isset($bouton)<tr><td style="padding:6px 24px 22px"><a href="{{ $bouton['lien'] }}" style="display:inline-block;background:#ff6a00;color:#17243b;padding:15px 26px;border-radius:10px;text-decoration:none;font-weight:bold;font-size:17px">{{ $bouton['texte'] }}</a></td></tr>@endisset
@isset($note)<tr><td style="padding:0 24px 18px"><p style="margin:0;font-size:14px;color:#526077">{{ $note }}</p></td></tr>@endisset
<tr><td style="background:#fff8f0;border-top:1px solid #ffeadb;padding:16px 24px;font-size:13px;color:#526077">Message automatique d'Avelis Séjours.@if($legale)<br>{{ $legale }}@endif</td></tr>
</table></td></tr></table>
</body></html>
