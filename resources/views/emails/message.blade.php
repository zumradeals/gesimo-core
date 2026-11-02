@component('emails.gabarit', ['titre' => $sujet, 'bouton' => $bouton])
{!! preg_replace('/<br \/>\s*<br \/>/', '</p><p style="margin:0 0 14px">', '<p style="margin:0 0 14px">'.nl2br(e($texte)).'</p>') !!}
@endcomponent
