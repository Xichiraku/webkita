{{--
    Modal Alpine. Pemakaian:
    <div x-data="{ open: false }"> ... <x-modal labelledby="judul" class="w-[min(92vw,30rem)"> ... </x-modal> </div>
    Variabel `open` dibaca dari scope Alpine induk; tutup dengan `open = false`.
--}}
@props(['labelledby' => null, 'show' => 'open', 'close' => null, 'width' => 'w-[min(92vw,30rem)]'])
@php($close = $close ?? $show.' = false')
<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak x-transition.opacity
         x-effect="document.documentElement.style.overflow = ({{ $show }}) ? 'hidden' : ''"
         @keydown.escape.window="{{ $close }}"
         @mousedown.self="{{ $close }}"
         class="modal-backdrop fixed inset-0 z-[100] grid place-items-center overflow-y-auto p-4">
        <div role="dialog" aria-modal="true" @if ($labelledby) aria-labelledby="{{ $labelledby }}" @endif tabindex="-1"
             x-trap.noscroll="{{ $show }}"
             {{ $attributes->class(['animate-rise rounded-3xl bg-white text-ink-900 shadow-lift outline-none', $width]) }}>
            {{ $slot }}
        </div>
    </div>
</template>
