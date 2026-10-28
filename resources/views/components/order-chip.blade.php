@props(['status', 'admin' => false])
@php($s = $status instanceof \App\Enums\ProjectStatus ? $status : \App\Enums\ProjectStatus::tryFrom((string) $status) ?? \App\Enums\ProjectStatus::Pending)
<span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold {{ $s->chip() }}">
    <x-dynamic-component :component="$s->icon()" class="h-3.5 w-3.5" aria-hidden="true" />
    {{ $admin ? $s->adminLabel() : $s->label() }}
</span>
