@if($r)
    @if($r['ok'])<x-ui.alerte type="succes">✔ <b>Réussi.</b> {{ $r['message'] }}</x-ui.alerte>@else<x-ui.alerte type="erreur">✖ <b>Échec.</b> {{ $r['message'] }}</x-ui.alerte>@endif
@endif
