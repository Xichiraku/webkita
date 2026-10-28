{{-- Input teks bergaya desain, dengan ikon opsional di kiri. --}}
@props(['id', 'label', 'icon' => null, 'required' => true, 'error' => null, 'hint' => null])
<div>
    <label for="{{ $id }}" class="{{ Ui::LABEL }}">
        {{ $label }} @if ($required)<span class="text-[#B91C1C]" aria-hidden="true">*</span>@elseif ($hint)<span class="font-medium text-ink-500">({{ $hint }})</span>@endif
    </label>
    <div class="relative">
        @if ($icon)
            <x-dynamic-component :component="'lucide-'.$icon" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" aria-hidden="true" />
        @endif
        <input id="{{ $id }}" name="{{ $id }}" @if ($error) aria-invalid="true" @endif aria-describedby="{{ $id }}-err"
               {{ $attributes->class([Ui::INPUT, 'pl-11' => $icon]) }}>
    </div>
    <x-field-error :id="$id.'-err'" :messages="$error" />
</div>
