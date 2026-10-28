<div x-data role="status" aria-live="polite" class="pointer-events-none fixed inset-x-0 bottom-6 z-[120] flex justify-center px-4">
    <template x-if="$store.toast.item">
        <div :key="$store.toast.item.id" class="toast-in pointer-events-auto flex items-center gap-2.5 rounded-xl bg-ink-900 px-5 py-3 text-sm font-semibold text-white shadow-lift">
            <template x-if="$store.toast.item.kind === 'err'">
                <x-lucide-circle-alert class="h-5 w-5 shrink-0 text-[#FCA5A5]" aria-hidden="true" />
            </template>
            <template x-if="$store.toast.item.kind !== 'err'">
                <x-lucide-circle-check class="h-5 w-5 shrink-0 text-[#86EFAC]" aria-hidden="true" />
            </template>
            <span x-text="$store.toast.item.msg"></span>
        </div>
    </template>
</div>
@if (session('toast'))
    <script>document.addEventListener('alpine:initialized', () => Alpine.store('toast').show(@js(session('toast'))));</script>
@endif
