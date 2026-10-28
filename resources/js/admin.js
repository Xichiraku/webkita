/**
 * State & aksi panel admin. Data dimuat sekali dari /api/* lalu dipakai semua bagian halaman.
 * Dipakai di layout admin: <body x-data="adminApp(config)">.
 */
const PER_PAGE = 10;

const STATUS = {
    pending: { label: 'Menunggu', chip: 'bg-[#FEF3C7] text-[#B45309]', dot: '#D97706' },
    approved: { label: 'Disetujui', chip: 'bg-brand-100 text-brand-800', dot: '#2559CC' },
    process: { label: 'Diproses', chip: 'bg-[#CFFAFE] text-[#0E7490]', dot: '#0E7490' },
    done: { label: 'Selesai', chip: 'bg-[#DCFCE7] text-[#15803D]', dot: '#15803D' },
    rejected: { label: 'Ditolak', chip: 'bg-[#FEE2E2] text-[#B91C1C]', dot: '#DC2626' },
};
// Perpindahan yang bisa dipilih admin secara manual. approved->process dan process->done
// diatur otomatis oleh sistem (Project Workspace).
const NEXT = {
    pending: ['pending', 'approved', 'rejected'],
    approved: ['approved', 'rejected'],
    process: ['process'],
    done: ['done'],
    rejected: ['rejected'],
};
const TRX = {
    pending: { label: 'Pending', chip: 'bg-[#FEF3C7] text-[#B45309]' },
    paid: { label: 'Lunas', chip: 'bg-[#DCFCE7] text-[#15803D]' },
    failed: { label: 'Gagal', chip: 'bg-[#FEE2E2] text-[#B91C1C]' },
    challenge: { label: 'Challenge', chip: 'bg-[#E0E7FF] text-[#3730A3]' },
};
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const num = (v) => {
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return v;
    const d = String(v).replace(/\D/g, '');
    return d ? parseInt(d, 10) : 0;
};

export default function registerAdmin(Alpine) {
    Alpine.data('adminApp', (config) => ({
        // data
        orders: [], users: [], trx: [], plans: [],
        loading: true, error: '', busyId: null,
        // UI
        sideOpen: false, detail: null, rejectTarget: null, rejectReason: '',
        categories: config.categories, meId: config.meId, isAdmin: config.isAdmin, devChoice: '',
        // konstanta untuk template
        STATUS, NEXT, TRX, PER_PAGE,

        async init() {
            await this.fetchAll();
            this.listen();
        },

        /** Update langsung (Laravel Reverb) di channel khusus staf. */
        listen() {
            if (!window.Echo) return;
            window.Echo.private('admin.orders')
                .listen('.project.created', (e) => {
                    if (this.orders.some((o) => o.id === e.project.id)) return;
                    this.orders.unshift(e.project);
                    this.toast(`Orderan baru: "${e.project.project_title}" dari ${e.project.contact_name}.`);
                })
                .listen('.project.updated', (e) => {
                    const o = this.orders.find((x) => x.id === e.id);
                    if (!o) return;
                    const changed = o.status !== e.status;
                    Object.assign(o, { status: e.status, reject_reason: e.reject_reason, plan_price_range: e.plan_price_range, invoice_number: e.invoice_number, invoice_issued_at: e.invoice_issued_at, updated_at: e.updated_at });
                    if (changed) this.toast(`Status "${o.project_title}" kini: ${this.st(e.status).label}.`);
                })
                .listen('.payment.changed', (e) => {
                    const i = this.trx.findIndex((t) => t.id === e.id);
                    if (i === -1) this.trx.unshift(e); else Object.assign(this.trx[i], e);
                    if (e.status === 'paid') this.toast(`Pembayaran ${this.rp(e.amount)} diterima (${e.order_id}).`);
                });
        },

        async fetchAll() {
            this.loading = true;
            this.error = '';
            const calls = [
                ['orders', 'GET', '/api/projects'],
                ['users', 'GET', '/api/users'],
                ['trx', 'GET', '/api/payments'],
                ['plans', 'GET', '/api/pricing'],
            ];
            const results = await Promise.allSettled(calls.map(([, m, u]) => api(m, u)));
            results.forEach((r, i) => {
                if (r.status === 'fulfilled') this[calls[i][0]] = r.value.data ?? [];
                else this.error = r.reason?.message || 'Sebagian data gagal dimuat';
            });
            this.loading = false;
            return !this.error;
        },

        async refresh() {
            const ok = await this.fetchAll();
            this.toast(ok ? 'Data diperbarui.' : 'Sebagian data gagal dimuat.', ok ? 'ok' : 'err');
        },

        toast(msg, kind = 'ok') { Alpine.store('toast').show(msg, kind); },

        // ===== helper tampilan =====
        rp(n) { return window.rp(n); },
        st(s) { return STATUS[s] || STATUS.pending; },
        tx(s) { return TRX[s] || TRX.pending; },
        catLabel(v) { return this.categories[v] || v || '–'; },
        priceOf(o) { return o.plan_price_range ? num(o.plan_price_range) : 0; },
        fmtDate(s) {
            if (!s) return '–';
            const d = new Date(s);
            return Number.isNaN(d.getTime()) ? '–' : `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
        },
        userName(u) { return [u.first_name, u.last_name].filter(Boolean).join(' ') || u.username || '–'; },
        initials(name = '') {
            const w = name.trim().split(/\s+/).filter(Boolean);
            return w.length ? ((w[0][0] || '') + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase() : '?';
        },
        waLink(phone = '') { return 'https://wa.me/' + String(phone).replace(/\D/g, '').replace(/^0/, '62'); },
        countStatus(k) { return k === 'all' ? this.orders.length : this.orders.filter((o) => o.status === k).length; },

        // ===== ringkasan dashboard =====
        get pendingCount() { return this.orders.filter((o) => o.status === 'pending').length; },
        get needAction() { return this.pendingCount + this.orders.filter((o) => o.status === 'approved').length; },
        get revenue() { return this.trx.filter((t) => t.status === 'paid').reduce((s, t) => s + Number(t.amount || 0), 0); },
        get actionList() { return this.orders.filter((o) => ['pending', 'approved'].includes(o.status)).slice(0, 6); },
        categoryStats() {
            const m = {};
            this.orders.forEach((o) => { m[o.category] = (m[o.category] || 0) + 1; });
            return Object.entries(m).sort((a, b) => b[1] - a[1]).map(([k, n]) => ({ key: k, label: this.catLabel(k), n }));
        },
        monthly() {
            const out = [];
            const now = new Date();
            for (let i = 5; i >= 0; i--) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
                out.push({ key, label: MONTHS[d.getMonth()], n: this.orders.filter((o) => (o.created_at || '').startsWith(key)).length });
            }
            return out;
        },

        // ===== pagination generik =====
        paginate(rows, page) {
            const pages = Math.max(1, Math.ceil(rows.length / PER_PAGE));
            const cur = Math.min(Math.max(1, page), pages);
            const from = (cur - 1) * PER_PAGE;
            return {
                items: rows.slice(from, from + PER_PAGE), page: cur, pages,
                info: rows.length ? `Menampilkan ${from + 1}–${Math.min(rows.length, from + PER_PAGE)} dari ${rows.length}` : 'Tidak ada data',
                nums: (() => {
                    const n = [];
                    for (let i = 1; i <= pages; i++) {
                        if (i === 1 || i === pages || Math.abs(i - cur) <= 1) n.push(i);
                        else if (n[n.length - 1] !== '…') n.push('…');
                    }
                    return n;
                })(),
            };
        },
        matches(q, ...fields) {
            q = q.trim().toLowerCase();
            return !q || fields.join(' ').toLowerCase().includes(q);
        },

        // ===== aksi orderan =====
        pickStatus(order, to) {
            if (to === order.status) return;
            if (to === 'rejected') { this.rejectReason = ''; this.rejectTarget = order; } else this.setStatus(order, to);
        },
        async setStatus(order, to, reason = '') {
            this.busyId = order.id;
            try {
                const res = await api('PATCH', `/api/projects/${order.id}/status`, to === 'rejected' ? { status: to, reason } : { status: to });
                Object.assign(order, res.data);
                if (this.detail?.id === order.id) this.detail = order;
                this.toast(`Status "${order.project_title}" diubah menjadi ${this.st(to).label}.`);
                return true;
            } catch (e) {
                this.toast(`Gagal mengubah status: ${e.message}`, 'err');
                return false;
            } finally { this.busyId = null; }
        },
        async confirmReject() {
            const o = this.rejectTarget;
            this.rejectTarget = null;
            await this.setStatus(o, 'rejected', this.rejectReason.trim());
        },
        async setPrice(order, value) {
            const n = num(value);
            if (!n) { this.toast('Masukkan harga yang valid.', 'err'); return false; }
            try {
                const res = await api('PATCH', `/api/projects/${order.id}/price`, { plan_price_range: String(n) });
                Object.assign(order, res.data);
                this.toast(`Harga ${order.project_title} diatur ke ${this.rp(n)}.`);
                return true;
            } catch (e) {
                this.toast(`Gagal mengatur harga: ${e.message}`, 'err');
                return false;
            }
        },

        // ===== developer proyek =====
        get staff() { return this.users.filter((u) => ['admin', 'developer'].includes(u.role) && u.is_aktif === 'Y'); },
        developerName(o) {
            if (!o.developer_id) return null;
            const u = this.users.find((x) => x.id === o.developer_id);
            return u ? this.userName(u) : `#${o.developer_id}`;
        },
        canAssign(o) { return this.isAdmin && ['approved', 'process'].includes(o.status); },
        async assignDeveloper(order, developerId) {
            try {
                const res = await api('PATCH', `/api/projects/${order.id}/developer`, { developer_id: developerId ? Number(developerId) : null });
                Object.assign(order, { developer_id: res.data.developer_id, assigned_at: res.data.assigned_at });
                this.toast(developerId ? `Developer ditugaskan ke "${order.project_title}".` : `Developer dilepas dari "${order.project_title}".`);
                return true;
            } catch (e) {
                this.toast(`Gagal menugaskan developer: ${e.message}`, 'err');
                return false;
            }
        },

        // ===== pengguna =====
        async deleteUser(u) {
            if (!confirm(`Hapus pengguna "${this.userName(u)}" (@${u.username})?`)) return;
            try {
                await api('DELETE', `/api/users/${encodeURIComponent(u.username)}`);
                this.users = this.users.filter((x) => x.id !== u.id);
                this.toast('Pengguna dihapus.');
            } catch (e) { this.toast(`Gagal menghapus: ${e.message}`, 'err'); }
        },

        // ===== paket layanan =====
        blankPlan() {
            return { id: null, title: '', tier: '', min_price: '', max_price: '', price_range: '', timeline: '', badge: '', description: '', upgrade_note: '', sort_order: this.plans.length + 1, is_active: true, features: [], not_included: [] };
        },
        editPlan(p) {
            return {
                ...p, max_price: p.max_price ?? '',
                features: (p.features || []).map((f) => f.feature),
                not_included: (p.not_included || []).map((f) => f.feature),
            };
        },
        async savePlan(f) {
            const min = num(f.min_price), max = f.max_price === '' || f.max_price === null ? null : num(f.max_price);
            const rpFmt = (n) => this.rp(n);
            const body = {
                title: f.title.trim(), tier: f.tier.trim().toLowerCase(), min_price: min, max_price: max,
                price_range: f.price_range.trim() || (max === null ? `Mulai ${rpFmt(min)}` : `${rpFmt(min)} – ${rpFmt(max)}`),
                timeline: f.timeline || null, badge: f.badge || null, description: f.description || null, upgrade_note: f.upgrade_note || null,
                sort_order: Number(f.sort_order) || 0, is_active: !!f.is_active,
                features: f.features.map((x) => x.trim()).filter(Boolean).map((feature, i) => ({ feature, sort_order: i })),
                not_included: f.not_included.map((x) => x.trim()).filter(Boolean).map((feature, i) => ({ feature, sort_order: i })),
            };
            try {
                const res = f.id ? await api('PUT', `/api/pricing/${f.id}`, body) : await api('POST', '/api/pricing', body);
                this.plans = f.id ? this.plans.map((p) => (p.id === f.id ? res.data : p)) : [...this.plans, res.data];
                this.plans.sort((a, b) => a.sort_order - b.sort_order);
                this.toast(f.id ? 'Paket diperbarui.' : 'Paket dibuat.');
                return true;
            } catch (e) { this.toast(e.message, 'err'); return false; }
        },
        async togglePlan(p) {
            try {
                const res = await api('PATCH', `/api/pricing/${p.id}/toggle-active`);
                p.is_active = res.data.is_active;
                this.toast(`Paket ${p.title} ${p.is_active ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) { this.toast(e.message, 'err'); }
        },
        async deletePlan(p) {
            if (!confirm(`Hapus paket "${p.title}"? Tindakan ini tidak dapat dibatalkan.`)) return;
            try {
                await api('DELETE', `/api/pricing/${p.id}`);
                this.plans = this.plans.filter((x) => x.id !== p.id);
                this.toast('Paket dihapus.');
            } catch (e) { this.toast(e.message, 'err'); }
        },
        planUsage(p) { return this.orders.filter((o) => (o.plan_title || '').toLowerCase() === p.title.toLowerCase()).length; },
    }));
}
