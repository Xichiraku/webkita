<x-layouts.site title="Proyek Saya">
<main class="relative overflow-hidden bg-ink-50">
    <div class="pointer-events-none absolute -left-24 top-0 h-72 w-72 animate-blob rounded-full bg-brand-100 opacity-70 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-5xl px-5 pb-20 pt-10 sm:px-8 sm:pt-14">
        <div class="animate-rise">
            <h1 class="text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Proyek Saya</h1>
            <p class="mt-1.5 text-ink-600">Proyek yang ditugaskan kepada Anda. Workspace aktif setelah klien membayar.</p>
        </div>

        <div class="mt-8 space-y-4" x-data="{ unread: @js((object) $unread), init() { userChannel()?.listen('.chat.unread', (e) => { this.unread = { ...this.unread, [e.project_id]: e.unread }; }); } }">
            @forelse ($projects as $p)
                @php($paid = $p->isPaymentConfirmed())
                <article class="card-in rounded-2xl border border-ink-200 bg-white p-5 shadow-soft transition hover:border-brand-300 hover:shadow-lift sm:p-6" style="--d: {{ $loop->index * 70 }}ms">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold tracking-wide text-ink-600">#{{ $p->id }}</p>
                            <h2 class="mt-0.5 break-words text-lg font-extrabold text-ink-900">{{ $p->project_title }}</h2>
                            <p class="mt-1 text-sm text-ink-600">Klien: {{ $p->user->name }} · Ditugaskan {{ Ui::date($p->assigned_at) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @unless ($paid)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#FEF3C7] px-3 py-1 text-xs font-bold text-[#B45309]"><x-lucide-clock-3 class="h-3.5 w-3.5" aria-hidden="true" /> Menunggu pembayaran</span>
                            @endunless
                            <x-order-chip :status="$p->status" admin />
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1.5 flex items-center justify-between text-sm"><span class="font-semibold text-ink-600">Progres</span><span class="font-extrabold text-ink-900">{{ $p->progress_percent }}%</span></div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-ink-100" role="progressbar" aria-valuenow="{{ $p->progress_percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres {{ $p->project_title }}">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ $p->progress_percent }}%"></div>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end border-t border-ink-100 pt-4">
                        <a href="{{ route('developer.projects.show', $p) }}" class="px-4 py-2.5 text-sm {{ Ui::BTN_PRIMARY }}">
                            <x-lucide-layout-dashboard class="h-4 w-4" aria-hidden="true" /> Buka Workspace
                            <span x-show="unread[{{ $p->id }}] > 0" x-cloak class="grid min-w-[1.25rem] place-items-center rounded-full bg-[#EF4444] px-1.5 text-[11px] font-extrabold leading-5 text-white" x-text="unread[{{ $p->id }}]" role="img" :aria-label="unread[{{ $p->id }}] + ' pesan chat belum dibaca'"></span>
                        </a>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-ink-200 bg-white px-6 py-14 text-center shadow-soft">
                    <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-lucide-package-open class="h-8 w-8" aria-hidden="true" /></span>
                    <h2 class="mt-5 text-lg font-extrabold text-ink-900">Belum ada proyek yang ditugaskan</h2>
                    <p class="mx-auto mt-1.5 max-w-sm text-ink-600">Proyek muncul di sini setelah admin menugaskannya kepada Anda.</p>
                </div>
            @endforelse
        </div>
    </div>
</main>
</x-layouts.site>
