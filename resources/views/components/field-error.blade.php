@props(['id' => null, 'messages' => null])
@php($msg = is_array($messages) ? ($messages[0] ?? null) : $messages)
@if ($msg)
    <p @if ($id) id="{{ $id }}" @endif class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-[#B91C1C]" role="alert">
        <x-lucide-circle-alert class="h-4 w-4 shrink-0" aria-hidden="true" />
        <span>{{ $msg }}</span>
    </p>
@endif
