@props(['note'])
<span class="whitespace-nowrap text-amber-500" role="img" aria-label="{{ $note }} sur 5">{{ str_repeat('★', (int) round($note)) }}<span class="text-trait">{{ str_repeat('★', 5 - (int) round($note)) }}</span></span>
