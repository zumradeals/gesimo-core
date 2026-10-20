@props(['type' => 'info'])
@php
$styles = [
    'erreur' => 'bg-red-50 text-red-800 border-red-200',
    'info' => 'bg-blue-50 text-blue-800 border-blue-200',
    'attention' => 'bg-amber-50 text-amber-800 border-amber-200',
    'succes' => 'bg-green-50 text-green-800 border-green-200',
];
@endphp
<div role="{{ $type === 'erreur' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-[15px] '.($styles[$type] ?? $styles['info'])]) }}>{{ $slot }}</div>
