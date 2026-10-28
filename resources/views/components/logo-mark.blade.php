@props(['class' => 'h-8 w-8 rounded-lg', 'icon' => 'h-[18px] w-[18px]'])
<span {{ $attributes->class(['grid place-items-center bg-brand-600 text-white', $class]) }}>
    <x-lucide-code-xml class="{{ $icon }}" aria-hidden="true" />
</span>
