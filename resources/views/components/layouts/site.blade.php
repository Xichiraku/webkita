@props(['title' => null])
@php
    $user = auth()->user();
    $path = request()->path();
    $links = [
        ['name' => 'Beranda', 'href' => url('/'), 'active' => request()->is('/')],
        ['name' => 'Cara Kerja', 'href' => url('/#cara-kerja'), 'active' => request()->is('carakerja')],
        ...($user ? [['name' => 'Orderan', 'href' => route('orderan'), 'active' => request()->is('orderan*')]] : []),
        ...($user?->isStaff() ? [['name' => 'Proyek Saya', 'href' => route('developer.projects'), 'active' => request()->is('developer*')]] : []),
        ['name' => 'Harga', 'href' => route('harga'), 'active' => request()->is('harga*')],
    ];
    $linkBase = 'rounded-lg px-3.5 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400';
    $linkIdle = 'font-semibold text-ink-700 hover:bg-brand-50 hover:text-brand-700';
    $linkOn = 'bg-brand-50 font-bold text-brand-700';
@endphp
<x-layouts.base :title="$title">
    <div class="flex min-h-screen flex-col bg-white">
        <a href="#utama" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-brand-700 focus:px-4 focus:py-2 focus:text-white">Lewati ke konten utama</a>

        {{-- ============ NAVBAR ============ --}}
        <header x-data="{ mobile: false, menu: false, scrolled: false }"
                x-init="scrolled = window.scrollY > 8"
                @scroll.window.passive="scrolled = window.scrollY > 8"
                @keydown.escape.window="mobile = false; menu = false"
                :class="scrolled ? 'border-ink-200 shadow-soft' : 'border-transparent'"
                class="sticky top-0 z-50 border-b bg-white/85 backdrop-blur transition-shadow duration-300">
            <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-5 sm:px-8">
                <x-logo />

                <div class="flex items-center gap-1">
                    <nav class="hidden items-center gap-1 md:flex" aria-label="Navigasi utama">
                        @foreach ($links as $l)
                            <a href="{{ $l['href'] }}" @if ($l['active']) aria-current="page" @endif class="{{ $linkBase }} {{ $l['active'] ? $linkOn : $linkIdle }}">{{ $l['name'] }}</a>
                        @endforeach
                        @guest
                            <a href="{{ route('login') }}" class="ml-3 {{ $linkBase }} {{ $linkIdle }}">Masuk</a>
                            <a href="{{ route('register') }}" class="ml-1 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-lift focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-400 motion-reduce:hover:translate-y-0">Daftar</a>
                        @endguest
                    </nav>

                    @auth
                        <div class="relative ml-2" @click.outside="menu = false">
                            <button type="button" @click="menu = !menu"
                                    class="grid h-10 w-10 place-items-center rounded-full bg-brand-600 text-white shadow-sm transition hover:bg-brand-700 hover:shadow-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-400"
                                    aria-haspopup="menu" :aria-expanded="menu" aria-label="Menu akun">
                                <x-lucide-user class="h-5 w-5" aria-hidden="true" />
                            </button>
                            <div x-show="menu" x-cloak role="menu" aria-label="Akun" class="menu-in absolute right-0 top-[calc(100%+12px)] w-64 rounded-2xl border border-ink-200 bg-white p-1.5 shadow-lift">
                                <div class="px-3.5 py-3">
                                    <p class="truncate font-bold text-ink-900">{{ $user->name }}</p>
                                    <p class="truncate text-xs text-ink-600">{{ $user->email }}</p>
                                </div>
                                <div class="my-1 h-px bg-ink-200" role="separator"></div>
                                @if ($user->isStaff())
                                    <a role="menuitem" href="{{ route('admin.dashboard') }}" class="flex w-full items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-left text-sm font-semibold text-ink-800 transition hover:bg-brand-50">
                                        <x-lucide-layout-dashboard class="h-4 w-4" aria-hidden="true" /> Panel Admin
                                    </a>
                                @endif
                                <a role="menuitem" href="{{ route('profile.edit') }}" class="flex w-full items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-left text-sm font-semibold text-ink-800 transition hover:bg-brand-50">
                                    <x-lucide-user class="h-4 w-4" aria-hidden="true" /> Profil
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button role="menuitem" type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-left text-sm font-semibold text-[#B91C1C] transition hover:bg-[#FEE2E2]">
                                        <x-lucide-log-out class="h-4 w-4" aria-hidden="true" /> Keluar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endauth

                    <button type="button" @click="mobile = !mobile"
                            class="ml-1 grid h-11 w-11 place-items-center rounded-xl text-ink-800 transition hover:bg-brand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400 md:hidden"
                            aria-controls="mobile-menu" :aria-expanded="mobile" :aria-label="mobile ? 'Tutup menu' : 'Buka menu'">
                        <x-lucide-menu x-show="!mobile" class="h-6 w-6" aria-hidden="true" />
                        <x-lucide-x x-show="mobile" x-cloak class="h-6 w-6" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div id="mobile-menu" x-show="mobile" x-cloak class="border-t border-ink-200 bg-white md:hidden">
                <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-5 py-4" aria-label="Navigasi seluler">
                    @foreach ($links as $l)
                        <a href="{{ $l['href'] }}" @if ($l['active']) aria-current="page" @endif class="rounded-lg px-3 py-3 text-left {{ $l['active'] ? 'bg-brand-50 font-bold text-brand-700' : 'font-semibold text-ink-800 hover:bg-brand-50' }}">{{ $l['name'] }}</a>
                    @endforeach
                    @guest
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <a href="{{ route('login') }}" class="rounded-xl border border-ink-300 px-4 py-3 text-center font-bold text-ink-800">Masuk</a>
                            <a href="{{ route('register') }}" class="rounded-xl bg-brand-600 px-4 py-3 text-center font-bold text-white">Daftar</a>
                        </div>
                    @endguest
                </nav>
            </div>
        </header>

        <div id="utama" class="flex-grow">{{ $slot }}</div>

        {{-- ============ FOOTER ============ --}}
        <footer class="border-t border-ink-200 bg-white">
            <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <x-logo />
                        <p class="mt-4 max-w-[15rem] text-sm leading-relaxed text-ink-600">Hubungkan bisnis Anda dengan developer terbaik.</p>
                    </div>

                    @foreach ([
                        'Perusahaan' => ['Tentang Kami', 'Karir', 'Blog', 'Kontak'],
                        'Layanan' => ['Developer', 'E-commerce', 'Design', 'Konsultasi'],
                        'Dukungan' => ['Pusat Bantuan', 'FAQ', 'Syarat & Ketentuan', 'Kebijakan Privasi'],
                    ] as $title => $items)
                        <nav aria-label="{{ $title }}">
                            <h3 class="text-sm font-bold text-ink-900">{{ $title }}</h3>
                            <ul class="mt-4 space-y-3 text-sm text-ink-600">
                                @foreach ($items as $it)
                                    <li><a href="#" class="transition hover:text-brand-700 hover:underline">{{ $it }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                    @endforeach

                    <div>
                        <h3 class="text-sm font-bold text-ink-900">Konsultasi</h3>
                        <p class="mt-4 text-sm text-ink-600">Ada pertanyaan? Hubungi kami lewat WhatsApp.</p>
                        <a href="{{ Ui::waKonsultasi() }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 {{ Ui::FOCUS }}">
                            <x-lucide-message-circle class="h-4 w-4" aria-hidden="true" /> WhatsApp
                        </a>
                    </div>
                </div>

                <div class="mt-12 border-t border-ink-200 pt-6 text-center text-sm text-ink-600">
                    &copy; {{ date('Y') }} Webkita. Semua hak dilindungi.
                </div>
            </div>
        </footer>
    </div>
</x-layouts.base>
