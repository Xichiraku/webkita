@php
    $d = fn ($ms) => "--d: {$ms}ms";
    $services = [
        ['code-xml', 'Pembuatan Website Kustom', 'Dari nol hingga siap pakai, kami rancang website yang unik, optimal, dan sesuai dengan identitas merek Anda.'],
        ['shopping-cart', 'Integrasi E-commerce', 'Tingkatkan penjualan dengan toko online yang canggih, aman, dan terintegrasi dengan berbagai sistem pembayaran.'],
        ['shield-check', 'Keamanan & Perawatan', 'Pastikan website Anda selalu aman, cepat, dan bebas dari masalah dengan dukungan teknis 24/7 kami.'],
    ];
    $steps = [
        ['clipboard-list', 'Pesan & Ajukan Kebutuhan', 'Pilih layanan, hitung estimasi budget, dan kirim kebutuhan proyek Anda.'],
        ['user-check', 'Diteruskan ke Developer', 'Kebutuhan Anda diteruskan ke developer profesional yang sesuai.'],
        ['chart-no-axes-column-increasing', 'Pengerjaan & Pantau Progres', 'Pantau milestone dan chat langsung dengan developer lewat Project Workspace.'],
        ['presentation', 'Presentasi & Revisi', 'Tinjau hasilnya, ajukan revisi bila perlu, lalu setujui hasil akhir.'],
    ];
@endphp
<x-layouts.site title="Beranda">
<main class="bg-white">
    {{-- ================= HERO ================= --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -left-24 top-10 h-72 w-72 animate-blob rounded-full bg-brand-100 opacity-70 blur-3xl motion-reduce:animate-none" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-24 top-40 h-80 w-80 animate-blob rounded-full bg-brand-200/60 blur-3xl motion-reduce:animate-none" style="animation-delay: -6s" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-5 pb-20 pt-14 sm:px-8 lg:grid-cols-2 lg:gap-10 lg:pb-28 lg:pt-20">
            <div>
                <span class="reveal inline-flex items-center gap-2 rounded-full bg-brand-100 px-3.5 py-1.5 text-sm font-semibold text-brand-700">
                    <x-lucide-sparkles class="h-4 w-4" aria-hidden="true" /> Platform Teknologi #1
                </span>

                <h1 class="reveal mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight text-ink-900 sm:text-5xl lg:text-[3.5rem]" style="{{ $d(80) }}">
                    Hubungkan Bisnis Anda dengan
                    <span class="bg-gradient-to-r from-brand-600 to-brand-400 bg-clip-text text-transparent">Developer Terbaik</span>
                </h1>

                <p class="reveal mt-6 max-w-xl text-lg leading-relaxed text-ink-600" style="{{ $d(160) }}">
                    Platform marketplace yang menghubungkan pembeli dengan developer profesional. Kami mengelola pihak ketiga terpercaya untuk memastikan proyek website Anda berjalan lancar.
                </p>

                <div class="reveal mt-8 flex flex-col gap-3 sm:flex-row" style="{{ $d(240) }}">
                    <a href="{{ auth()->check() ? route('harga') : route('register') }}" class="group px-6 py-3.5 {{ Ui::BTN_PRIMARY }}">
                        Mulai Sekarang
                        <x-lucide-arrow-right class="h-5 w-5 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden="true" />
                    </a>
                    <a href="#cara-kerja" class="inline-flex items-center justify-center rounded-xl border-2 border-ink-200 bg-white px-6 py-3.5 font-bold text-ink-800 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700 {{ Ui::FOCUS }}">Pelajari Lebih Lanjut</a>
                </div>

                <ul class="reveal mt-10 space-y-4" style="{{ $d(320) }}">
                    <li class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide-users class="h-[18px] w-[18px]" aria-hidden="true" /></span>
                        <span class="leading-tight"><span class="block text-sm font-bold text-ink-900">100k+ Pengguna</span><span class="text-sm text-ink-600">Bergabung dengan Kami</span></span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide-clock class="h-[18px] w-[18px]" aria-hidden="true" /></span>
                        <span class="text-sm font-bold text-ink-900">Respon Cepat</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide-shield-check class="h-[18px] w-[18px]" aria-hidden="true" /></span>
                        <span class="text-sm font-bold text-ink-900">Garansi Keamanan</span>
                    </li>
                </ul>
            </div>

            {{-- Visual: ilustrasi Project Workspace --}}
            <div class="reveal relative mx-auto mb-10 w-full max-w-[520px]" style="{{ $d(200) }}">
                <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-brand-200/70 to-brand-50 blur-2xl" aria-hidden="true"></div>

                <div class="relative animate-float rounded-3xl border border-ink-200 bg-white p-5 shadow-lift motion-reduce:animate-none sm:p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5" aria-hidden="true">
                            <span class="h-2.5 w-2.5 rounded-full bg-ink-200"></span><span class="h-2.5 w-2.5 rounded-full bg-ink-200"></span><span class="h-2.5 w-2.5 rounded-full bg-ink-200"></span>
                        </div>
                        <span class="text-xs font-semibold text-ink-500">Project Workspace</span>
                    </div>

                    <div class="mt-5 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Proyek</p>
                            <h2 class="text-lg font-extrabold text-ink-900">Website Toko Batik</h2>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1 text-xs font-bold text-brand-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span> Berjalan
                        </span>
                    </div>

                    <div class="mt-5">
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="font-semibold text-ink-600">Progres keseluruhan</span>
                            <span class="font-extrabold text-brand-700">68%</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-ink-100" role="progressbar" aria-valuenow="68" aria-valuemin="0" aria-valuemax="100" aria-label="Progres keseluruhan proyek">
                            <div class="bar-fill h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="--w: 68%"></div>
                        </div>
                    </div>

                    <ul class="mt-6 space-y-3">
                        <li class="flex items-center justify-between gap-3 rounded-2xl bg-ink-50 p-3.5">
                            <span class="flex min-w-0 items-center gap-3"><x-lucide-circle-check class="h-5 w-5 shrink-0 text-[#15803D]" aria-hidden="true" /><span class="truncate text-sm font-bold text-ink-900">Desain antarmuka</span></span>
                            <span class="shrink-0 rounded-full bg-[#DCFCE7] px-2.5 py-1 text-xs font-bold text-[#15803D]">Disetujui</span>
                        </li>
                        <li class="flex items-center justify-between gap-3 rounded-2xl bg-ink-50 p-3.5 ring-2 ring-brand-200">
                            <span class="flex min-w-0 items-center gap-3"><x-lucide-clock-3 class="h-5 w-5 shrink-0 text-brand-600" aria-hidden="true" /><span class="truncate text-sm font-bold text-ink-900">Pengembangan frontend</span></span>
                            <span class="shrink-0 rounded-full bg-brand-100 px-2.5 py-1 text-xs font-bold text-brand-700">Menunggu review</span>
                        </li>
                        <li class="flex items-center justify-between gap-3 rounded-2xl bg-ink-50 p-3.5">
                            <span class="flex min-w-0 items-center gap-3"><x-lucide-circle-dashed class="h-5 w-5 shrink-0 text-ink-500" aria-hidden="true" /><span class="truncate text-sm font-bold text-ink-900">Integrasi pembayaran</span></span>
                            <span class="shrink-0 rounded-full bg-ink-200 px-2.5 py-1 text-xs font-bold text-ink-700">Belum mulai</span>
                        </li>
                    </ul>
                </div>

                <div class="absolute -bottom-12 -left-4 hidden animate-float-slow items-center gap-3 rounded-2xl border border-ink-200 bg-white px-4 py-3 shadow-lift motion-reduce:animate-none sm:flex">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-100 text-brand-700"><x-lucide-message-circle class="h-[18px] w-[18px]" aria-hidden="true" /></span>
                    <span class="leading-tight"><span class="block text-xs font-semibold text-ink-500">Chat developer</span><span class="text-sm font-bold text-ink-900">Hasil milestone 2 diunggah</span></span>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= LAYANAN ================= --}}
    <section id="layanan" class="py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Layanan Kami</h2>
                <p class="mt-4 text-lg text-ink-600">Kami menawarkan berbagai layanan pembuatan website yang profesional dan disesuaikan dengan kebutuhan bisnis Anda.</p>
            </div>

            <div class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach ($services as $i => [$icon, $title, $desc])
                    <article class="reveal group rounded-3xl border border-ink-200 bg-white p-7 shadow-soft transition duration-300 hover:-translate-y-1.5 hover:border-brand-300 hover:shadow-lift motion-reduce:transition-none motion-reduce:hover:translate-y-0" style="{{ $d($i * 100) }}">
                        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-100 text-brand-700 transition duration-300 group-hover:bg-brand-600 group-hover:text-white"><x-dynamic-component :component="'lucide-'.$icon" class="h-6 w-6" aria-hidden="true" /></span>
                        <h3 class="mt-5 text-xl font-bold text-ink-900">{{ $title }}</h3>
                        <p class="mt-2 leading-relaxed text-ink-600">{{ $desc }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= CARA KERJA ================= --}}
    <section id="cara-kerja" class="scroll-mt-20 bg-ink-50 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Cara Kerja</h2>
                <p class="mt-4 text-lg text-ink-600">Empat langkah sederhana dari pemesanan hingga website Anda siap, dengan progres yang bisa Anda pantau kapan saja.</p>
            </div>

            <ol class="mt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                @foreach ($steps as $i => [$icon, $title, $desc])
                    <li class="reveal step-line relative text-center" style="{{ $d($i * 100) }}">
                        <span class="relative mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-600 text-white shadow-soft"><x-dynamic-component :component="'lucide-'.$icon" class="h-6 w-6" aria-hidden="true" /></span>
                        <p class="mt-4 text-sm font-bold text-brand-700">Langkah {{ $i + 1 }}</p>
                        <h3 class="mt-1 text-lg font-bold text-ink-900">{{ $title }}</h3>
                        <p class="mx-auto mt-2 max-w-[16rem] text-ink-600">{{ $desc }}</p>
                    </li>
                @endforeach
            </ol>

            <p class="reveal mt-12 text-center"><a href="{{ route('carakerja') }}" class="font-bold text-brand-700 hover:underline">Lihat alur lengkap proses kerja kami →</a></p>
        </div>
    </section>

    {{-- ================= HARGA ================= --}}
    <section id="harga" class="scroll-mt-20 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="reveal mx-auto flex max-w-4xl flex-col items-center gap-8 rounded-3xl border border-ink-200 bg-white p-8 text-center shadow-soft sm:p-12 md:flex-row md:text-left">
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-brand-100 text-brand-700"><x-lucide-calculator class="h-7 w-7" aria-hidden="true" /></span>
                <div class="flex-1">
                    <h2 class="text-2xl font-extrabold tracking-tight text-ink-900 sm:text-3xl">Harga sesuai kebutuhan proyek Anda</h2>
                    <p class="mt-2 text-ink-600">Gunakan kalkulator budget untuk mendapatkan estimasi biaya sebelum memesan. Tanpa kejutan di tengah proyek.</p>
                </div>
                <a href="{{ route('harga') }}" class="shrink-0 px-6 py-3.5 shadow-none {{ Ui::BTN_PRIMARY }}">Hitung Estimasi <x-lucide-arrow-right class="h-5 w-5" aria-hidden="true" /></a>
            </div>
        </div>
    </section>

    {{-- ================= CTA ================= --}}
    <section class="bg-ink-50 py-20 lg:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="reveal relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-900 to-brand-950 px-6 py-16 text-center shadow-lift sm:px-12 lg:py-20">
                <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 animate-blob rounded-full bg-brand-500/30 blur-3xl motion-reduce:animate-none" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-20 -left-10 h-64 w-64 animate-blob rounded-full bg-brand-400/20 blur-3xl motion-reduce:animate-none" style="animation-delay: -7s" aria-hidden="true"></div>
                <div class="relative">
                    <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Siap Memulai Proyek Website Anda?</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-brand-100">Jangan tunda lagi. Hubungi kami sekarang dan dapatkan konsultasi gratis untuk mewujudkan ide digital Anda.</p>
                    <a href="{{ Ui::waKonsultasi() }}" target="_blank" rel="noopener noreferrer" class="group mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-7 py-3.5 font-bold text-brand-700 shadow-soft transition hover:-translate-y-0.5 hover:bg-brand-50 hover:shadow-lift focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white motion-reduce:hover:translate-y-0">
                        Konsultasi Gratis
                        <x-lucide-arrow-right class="h-5 w-5 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden="true" />
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>
</x-layouts.site>
