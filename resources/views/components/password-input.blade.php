@props(['id', 'label', 'required' => true, 'error' => null])
<div x-data="{ show: false }">
    <label for="{{ $id }}" class="{{ Ui::LABEL }}">
        {{ $label }} @if ($required)<span class="text-[#B91C1C]" aria-hidden="true">*</span>@endif
    </label>
    <div class="relative">
        <x-lucide-lock class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" aria-hidden="true" />
        <input id="{{ $id }}" name="{{ $id }}" :type="show ? 'text' : 'password'" type="password"
               @if ($error) aria-invalid="true" @endif aria-describedby="{{ $id }}-err"
               {{ $attributes->class([Ui::INPUT, 'pl-11 pr-12']) }}>
        <button type="button" @click="show = !show"
                class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-ink-500 transition hover:bg-brand-50 hover:text-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400"
                :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'" :aria-pressed="show">
            <x-lucide-eye x-show="!show" class="h-5 w-5" aria-hidden="true" />
            <x-lucide-eye-off x-show="show" x-cloak class="h-5 w-5" aria-hidden="true" />
        </button>
    </div>
    <x-field-error :id="$id.'-err'" :messages="$error" />
</div>
