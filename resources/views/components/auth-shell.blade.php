{{--
    Kerangka halaman auth. `step` (0..2) menampilkan indikator langkah reset password;
    `back` = [url, label] untuk tautan kembali di atas kartu.
--}}
@props(['title' => null, 'subtitle' => null, 'back' => ['/', 'Kembali ke Beranda'], 'step' => null, 'maxWidth' => 'max-w-[29rem]', 'pageTitle' => null])
<x-layouts.base :title="$pageTitle ?? $title" bodyClass="relative min-h-screen bg-white font-sans text-ink-900">
    <div class="pointer-events-none fixed inset-0 overflow-hidden bg-gradient-to-b from-white to-ink-50" aria-hidden="true">
        <div class="absolute -left-24 top-10 h-80 w-80 animate-blob rounded-full bg-brand-100 opacity-80 blur-3xl"></div>
        <div class="absolute -right-24 bottom-10 h-96 w-96 animate-blob rounded-full bg-brand-200/60 blur-3xl" style="animation-delay: -7s"></div>
    </div>

    <main class="relative flex min-h-screen flex-col items-center justify-center px-5 py-10">
        <div class="w-full animate-rise {{ $maxWidth }}">
            @if ($back)
                <a href="{{ $back[0] }}" class="group mb-6 inline-flex items-center gap-2 rounded-md text-sm font-semibold text-ink-700 transition hover:text-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-400">
                    <x-lucide-arrow-left class="h-4 w-4 transition-transform group-hover:-translate-x-1" aria-hidden="true" /> {{ $back[1] }}
                </a>
            @endif

            <div x-data="{ shake: {{ $errors->any() ? 'true' : 'false' }} }" :class="shake ? 'animate-shake' : ''" class="rounded-3xl border border-ink-200 bg-white p-6 shadow-lift sm:p-9">
                @if ($step !== null)
                    <ol class="mb-7 flex items-center justify-center gap-2" aria-label="Langkah reset password">
                        @foreach (['Email', 'Kode OTP', 'Password baru'] as $i => $label)
                            @if ($i > 0)<li aria-hidden="true" class="h-px w-6 bg-ink-300"></li>@endif
                            <li class="flex items-center gap-2" @if ($i === $step) aria-current="step" @endif>
                                <span class="grid h-7 w-7 place-items-center rounded-full text-xs font-bold {{ $i === $step ? 'bg-brand-600 text-white' : ($i < $step ? 'bg-[#DCFCE7] text-[#15803D]' : 'bg-ink-100 text-ink-600') }}">
                                    @if ($i < $step)<x-lucide-check class="h-4 w-4" aria-hidden="true" />@else{{ $i + 1 }}@endif
                                </span>
                                <span class="hidden text-xs min-[420px]:inline {{ $i === $step ? 'font-bold text-ink-800' : 'font-semibold text-ink-600' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif

                @if ($title)
                    <div class="flex flex-col items-center text-center">
                        <x-logo-mark class="h-12 w-12 rounded-2xl shadow-soft" icon="h-6 w-6" />
                        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink-900">{{ $title }}</h1>
                        @if ($subtitle)<p class="mt-1.5 text-ink-600">{{ $subtitle }}</p>@endif
                    </div>
                @endif

                {{ $slot }}
            </div>

            {{ $footer ?? '' }}
        </div>
    </main>
</x-layouts.base>
