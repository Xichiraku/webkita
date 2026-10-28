@props(['type' => 'error'])
@if ($type === 'success')
    <p {{ $attributes->class(['flex items-start gap-2.5 rounded-xl bg-[#DCFCE7] p-3.5 text-sm font-semibold text-[#14532D]']) }} role="status">
        <x-lucide-circle-check class="mt-0.5 h-5 w-5 shrink-0 text-[#15803D]" aria-hidden="true" /> <span>{{ $slot }}</span>
    </p>
@else
    <p {{ $attributes->class(['flex items-start gap-2.5 rounded-xl bg-[#FEF2F2] p-3.5 text-sm font-semibold text-[#7F1D1D]']) }} role="alert">
        <x-lucide-circle-alert class="mt-0.5 h-5 w-5 shrink-0 text-[#B91C1C]" aria-hidden="true" /> <span>{{ $slot }}</span>
    </p>
@endif
