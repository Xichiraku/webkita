import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import registerAdmin from './admin';
import registerWorkspace from './workspace';

Alpine.plugin(focus);

window.Alpine = Alpine;

/**
 * Panggilan JSON ke /api/* dengan sesi login + CSRF.
 * Melempar Error(message) bila gagal (pesan dari server, atau error validasi pertama).
 */
window.api = async (method, url, body) => {
    const isForm = body instanceof FormData;
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        const err = new Error(firstError || data.message || `Permintaan gagal (${res.status})`);
        err.status = res.status;
        err.errors = data.errors || {};
        throw err;
    }
    return data;
};

/** Toast global: Alpine.store('toast').show('Pesan', 'ok' | 'err') */
Alpine.store('toast', {
    item: null,
    timer: null,
    show(msg, kind = 'ok') {
        clearTimeout(this.timer);
        this.item = { id: Date.now(), msg, kind };
        this.timer = setTimeout(() => (this.item = null), 3600);
    },
});

/** Angka "menghitung naik": <span x-data="countUp(120)" x-text="val"></span> */
Alpine.data('countUp', (to = 0, duration = 700) => ({
    val: 0,
    init() {
        if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || !to) {
            this.val = to;
            return;
        }
        const t0 = performance.now();
        const tick = (t) => {
            const k = Math.min(1, (t - t0) / duration);
            this.val = Math.round(to * (1 - Math.pow(1 - k, 3)));
            if (k < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    },
}));


/** Format rupiah: 1500000 -> "Rp 1.500.000" */
window.rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

/** Kalkulator budget (skala logaritmik) di halaman Harga */
Alpine.data('pricingCalc', (plans, loggedIn, orderUrl) => {
    const MIN = 500000, MAX = 100000000, STEPS = 1000;
    const LOGR = Math.log(MAX / MIN);
    const pos = (v) => Math.min(1, Math.max(0, Math.log(Math.max(v, MIN) / MIN) / LOGR));
    const BARS = ['bg-brand-300', 'bg-brand-500', 'bg-brand-700', 'bg-brand-950'];
    return {
        plans, STEPS, MAX,
        t: Math.round(STEPS * pos(13000000)),
        detail: null,
        loginPrompt: false,
        announce: '',
        get budget() {
            const v = MIN * Math.exp(LOGR * (this.t / STEPS));
            const step = v < 5e6 ? 1e5 : v < 25e6 ? 5e5 : 1e6;
            return Math.min(MAX, Math.max(MIN, Math.round(v / step) * step));
        },
        get current() {
            for (let i = this.plans.length - 1; i >= 0; i--) if (this.budget >= this.plans[i].minPrice) return this.plans[i];
            return this.plans[0];
        },
        get zones() {
            const th = this.plans.slice(1).map((p) => pos(p.minPrice));
            const edges = [0, ...th, 1];
            return { ticks: th, labels: this.plans.map((p, i) => ({ p, c: (edges[i] + edges[i + 1]) / 2 })) };
        },
        left: (p) => `calc(14px + (100% - 28px) * ${p.toFixed(4)})`,
        barOf(p) { return BARS[Math.min(this.plans.indexOf(p), BARS.length - 1)] || BARS[0]; },
        say() { this.announce = `Paket rekomendasi: ${this.current.title}, ${this.current.priceRange}`; },
        order(plan) {
            this.detail = null;
            if (!loggedIn) { this.loginPrompt = true; return; }
            window.location.href = `${orderUrl}?plan=${encodeURIComponent(plan.id)}`;
        },
    };
});

/** Muat Midtrans Snap (client key & mode dari meta tag di <head>) */
window.loadSnap = () => new Promise((resolve, reject) => {
    if (window.snap) return resolve();
    const key = document.querySelector('meta[name="midtrans-client-key"]')?.content;
    const prod = document.querySelector('meta[name="midtrans-production"]')?.content === '1';
    if (!key) return reject(new Error('Midtrans belum dikonfigurasi'));
    const s = document.createElement('script');
    s.src = prod ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
    s.setAttribute('data-client-key', key);
    s.onload = resolve;
    s.onerror = () => reject(new Error('Gagal memuat Midtrans'));
    document.body.appendChild(s);
});

/** Buat transaksi di server lalu buka popup Snap. Callback: onSuccess / onPending / onError / onClose */
window.payProject = async (projectId, customer, cb = {}) => {
    await window.loadSnap();
    const res = await api('POST', `/api/payments/create/${projectId}`, customer);
    window.snap.pay(res.data.snap_token, {
        onSuccess: () => cb.onSuccess?.(),
        onPending: () => cb.onPending?.(),
        onError: () => cb.onError?.(),
        onClose: () => cb.onClose?.(),
    });
};

registerAdmin(Alpine);
registerWorkspace(Alpine);

Alpine.start();

/** Animasi "muncul saat di-scroll" untuk semua `.reveal` */
const initReveal = () => {
    const items = Array.from(document.querySelectorAll('.reveal:not(.is-visible)'));
    if (!('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }
    const io = new IntersectionObserver(
        (entries) => entries.forEach((en) => {
            if (en.isIntersecting) {
                en.target.classList.add('is-visible');
                io.unobserve(en.target);
            }
        }),
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );
    items.forEach((el) => io.observe(el));
};
document.addEventListener('DOMContentLoaded', initReveal);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
