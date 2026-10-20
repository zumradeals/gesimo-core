@props(['statut'])
@php
$tons = [
    'attente' => 'bg-amber-100 text-amber-800',
    'succes' => 'bg-green-100 text-green-800',
    'info' => 'bg-blue-100 text-blue-800',
    'neutre' => 'bg-slate-200 text-slate-700',
    'danger' => 'bg-red-100 text-red-800',
];
@endphp
<span class="inline-block rounded-full px-3 py-0.5 text-[13px] font-bold {{ $tons[$statut->ton()] }}">{{ $statut->libelle() }}</span>
