@props(['href' => '/'])
<a href="{{ $href }}" {{ $attributes->class(['flex items-center gap-2 rounded-md text-xl font-extrabold tracking-tight text-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-400']) }}>
    <x-logo-mark />
    Webkita
</a>
