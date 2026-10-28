@props(['status'])
@php($s = $status instanceof \App\Enums\PaymentStatus ? $status : \App\Enums\PaymentStatus::tryFrom((string) $status) ?? \App\Enums\PaymentStatus::Pending)
<span title="{{ $s->note() }}" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold {{ $s->chip() }}">
    <x-dynamic-component :component="$s->icon()" class="h-3.5 w-3.5" aria-hidden="true" />
    {{ $s->label() }}
</span>
