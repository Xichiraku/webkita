/**
 * Komponen Alpine Project Workspace.
 */
const STATUS_CHIP = {
    pending: ['Menunggu', 'bg-[#FEF3C7] text-[#B45309]'],
    approved: ['Disetujui', 'bg-brand-100 text-brand-800'],
    process: ['Dalam Proses', 'bg-[#CFFAFE] text-[#0E7490]'],
    done: ['Selesai', 'bg-[#DCFCE7] text-[#15803D]'],
    rejected: ['Ditolak', 'bg-[#FEE2E2] text-[#B91C1C]'],
};

const MILESTONE_CHIP = {
    planned: ['Direncanakan', 'bg-ink-100 text-ink-700'],
    in_progress: ['Dikerjakan', 'bg-[#CFFAFE] text-[#0E7490]'],
    submitted: ['Menunggu review', 'bg-[#FEF3C7] text-[#B45309]'],
    revision: ['Revisi diminta', 'bg-[#FEE2E2] text-[#B91C1C]'],
    approved: ['Disetujui', 'bg-[#DCFCE7] text-[#15803D]'],
};

const rtf = new Intl.RelativeTimeFormat('id', { numeric: 'auto' });

export default function registerWorkspace(Alpine) {
    /**
     * Timeline riwayat aktivitas: terlama di atas -> terbaru di bawah.
     * `initial` = aktivitas terbaru (sudah terurut terlama dulu) dari server.
     */
    Alpine.data('activityTimeline', (projectId, initial, hasMore) => ({
        items: initial,
        hasMore,
        loading: false,
        error: '',
        newCount: 0,
        atBottom: true,
        announce: '',
        now: Date.now(),

        init() {
            this.$nextTick(() => this.scrollBottom());
            setInterval(() => { this.now = Date.now(); }, 30000);

            // Update langsung lewat Laravel Reverb (channel per proyek).
            window.Echo?.private(`project.${projectId}`).listen('.activity.created', (e) => this.push(e));
        },

        get box() { return this.$refs.box; },

        scrollBottom() {
            this.box.scrollTop = this.box.scrollHeight;
            this.atBottom = true;
            this.newCount = 0;
        },

        onScroll() {
            this.atBottom = this.box.scrollHeight - this.box.scrollTop - this.box.clientHeight < 24;
            if (this.atBottom) this.newCount = 0;
        },

        push(activity) {
            if (this.items.some((a) => a.id === activity.id)) return;
            // Urutan menurut id (bukan urutan kedatangan event, yang bisa terbalik).
            const at = this.items.findIndex((a) => a.id > activity.id);
            if (at === -1) this.items.push(activity); else this.items.splice(at, 0, activity);
            this.announce = activity.text;
            if (this.atBottom) this.$nextTick(() => this.scrollBottom());
            else this.newCount++;
        },

        async loadOlder() {
            if (this.loading || !this.items.length) return;
            this.loading = true;
            this.error = '';
            const prevHeight = this.box.scrollHeight;
            try {
                const res = await api('GET', `/api/projects/${projectId}/activities?before_id=${this.items[0].id}`);
                this.items = [...res.data.reverse(), ...this.items];
                this.hasMore = res.meta.has_more;
                this.$nextTick(() => { this.box.scrollTop = this.box.scrollHeight - prevHeight; });
            } catch (e) {
                this.error = e.message || 'Gagal memuat riwayat.';
            } finally {
                this.loading = false;
            }
        },

        icon(name) {
            return this.$refs.icons.querySelector(`[data-icon="${name}"]`)?.innerHTML ?? '';
        },

        chip(status) { return STATUS_CHIP[status] ?? ['–', 'bg-ink-100 text-ink-700']; },

        absolute(iso) {
            return new Date(iso).toLocaleString('id-ID', {
                timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false,
            });
        },

        relative(iso) {
            const sec = Math.round((new Date(iso).getTime() - this.now) / 1000);
            const abs = Math.abs(sec);
            if (abs < 45) return 'baru saja';
            if (abs < 3600) return rtf.format(Math.round(sec / 60), 'minute');
            if (abs < 86400) return rtf.format(Math.round(sec / 3600), 'hour');
            if (abs < 86400 * 7) return rtf.format(Math.round(sec / 86400), 'day');
            return this.absolute(iso);
        },
    }));

    /**
     * Tab Milestone: daftar milestone, form kelola (developer) dan update progres/hasil.
     * Klien hanya membaca. Data realtime lewat `.milestone.changed` dan `.update.created`.
     */
    Alpine.data('milestoneBoard', (projectId, initial, canManage, project) => ({
        canManage,
        project,
        milestones: initial,
        revising: null,
        reason: '',
        reasonError: '',
        editingLimit: false,
        limitDraft: '',
        limitError: '',
        open: null,
        updates: {},
        busy: false,
        error: '',
        draft: { title: '', description: '', due_date: '' },
        adding: false,
        editing: null,
        form: { type: 'progress', percent: 0, note: '', title: '', links: '', files: [] },
        formError: '',
        formErrors: {},
        today: new Date().toISOString().slice(0, 10),

        init() {
            window.Echo?.private(`project.${projectId}`)
                .listen('.milestone.changed', (e) => this.applyList(e.milestones, e.project))
                .listen('.update.created', (u) => this.pushUpdate(u))
                .listen('.update.reviewed', (e) => this.applyReview(e));

            window.dispatchEvent(new CustomEvent('workspace-attention', { detail: this.attention }));
            this.$watch('attention', (v) => window.dispatchEvent(new CustomEvent('workspace-attention', { detail: v })));

            // Klien langsung melihat hasil yang menunggu review.
            const waiting = this.milestones.find((m) => m.status === 'submitted');
            if (waiting && !this.canManage) this.toggle(waiting);
        },

        get projectDone() { return this.project.status === 'done'; },
        /** Titik pada tab: klien -> ada hasil menunggu review; developer -> ada revisi terbuka. */
        get attention() { return this.milestones.some((m) => m.status === (this.canManage ? 'revision' : 'submitted')); },
        get quotaText() {
            const { revisions_used: used, revision_limit: limit } = this.project;
            return limit === null ? `Revisi: tanpa batas (${used} terpakai)` : `Sisa revisi: ${Math.max(limit - used, 0)} dari ${limit}`;
        },
        get quotaLeft() { return this.project.revision_limit === null || this.project.revisions_used < this.project.revision_limit; },

        get done() { return this.milestones.filter((m) => m.status === 'approved').length; },
        get overall() {
            return this.milestones.length ? Math.round(this.milestones.reduce((a, m) => a + m.percent, 0) / this.milestones.length) : 0;
        },
        get summary() {
            return this.milestones.length ? `${this.done} dari ${this.milestones.length} milestone selesai · ${this.overall}%` : 'Belum ada milestone.';
        },

        chip(status) { return MILESTONE_CHIP[status] ?? MILESTONE_CHIP.planned; },
        fmtDate(d) { return d ? new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : ''; },
        fmtTime(iso) {
            return new Date(iso).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false });
        },
        fmtSize(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; },

        applyList(list, project) {
            this.milestones = list;
            if (project) {
                Object.assign(this.project, { status: project.status });
                window.dispatchEvent(new CustomEvent('workspace-progress', { detail: project }));
            }
        },

        // ── Peninjauan hasil (klien) ──
        pending(m) { return (this.updates[m.id]?.items ?? []).find((u) => u.type === 'result' && u.review_status === 'pending'); },
        reviewLabel(u) {
            return { pending: 'Menunggu review', approved: 'Disetujui', revision_requested: 'Revisi diminta' }[u.review_status] ?? '';
        },

        applyReview(e) {
            for (const cur of Object.values(this.updates)) {
                const u = cur.items.find((x) => x.id === e.update_id);
                if (u) u.review_status = e.review_status;
            }
            const i = this.milestones.findIndex((x) => x.id === e.milestone.id);
            if (i >= 0) this.milestones[i] = e.milestone;
            Object.assign(this.project, e.project);
            window.dispatchEvent(new CustomEvent('workspace-progress', { detail: e.project }));
        },

        async review(m, path, body, onOk) {
            const u = this.pending(m);
            if (!u || this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const res = await api('POST', `/api/updates/${u.id}/${path}`, body);
                this.applyReview({ update_id: u.id, review_status: res.data.update.review_status, milestone: res.data.milestone, project: res.data.project });
                onOk?.();
            } catch (e) {
                if (onOk) this.reasonError = e.message; else this.error = e.message;
            } finally {
                this.busy = false;
            }
        },

        async approve(m) {
            if (!confirm('Dengan menyetujui, milestone ini dinyatakan selesai dan tidak dapat dibuka kembali.')) return;
            await this.review(m, 'approve', undefined);
            if (this.milestones.find((x) => x.id === m.id)?.status === 'approved') Alpine.store('toast').show('Hasil disetujui');
        },

        openRevision(m) { this.revising = m; this.reason = ''; this.reasonError = ''; },

        async sendRevision() {
            const m = this.revising;
            if (this.reason.trim().length < 20) { this.reasonError = 'Jelaskan bagian yang perlu direvisi (minimal 20 karakter).'; return; }
            await this.review(m, 'request-revision', { reason: this.reason.trim() }, () => {
                this.revising = null;
                Alpine.store('toast').show('Permintaan revisi dikirim');
            });
        },

        // ── Kuota revisi (developer) ──
        startLimit() {
            this.limitDraft = this.project.revision_limit === null ? '' : String(this.project.revision_limit);
            this.limitError = '';
            this.editingLimit = true;
        },

        async saveLimit() {
            if (this.busy) return;
            this.busy = true;
            this.limitError = '';
            try {
                const raw = String(this.limitDraft).trim();
                const res = await api('PATCH', `/api/projects/${projectId}/revision-limit`, { limit: raw === '' ? null : Number(raw) });
                Object.assign(this.project, res.data);
                this.editingLimit = false;
                Alpine.store('toast').show('Kuota revisi diperbarui');
            } catch (e) {
                this.limitError = e.message;
            } finally {
                this.busy = false;
            }
        },

        canReceive(m) { return this.canManage && !this.projectDone && ['planned', 'in_progress', 'revision'].includes(m.status); },
        disabledReason(m) {
            if (this.projectDone) return 'Proyek sudah selesai.';
            if (m.status === 'submitted') return 'Menunggu review klien.';
            if (m.status === 'approved') return 'Milestone ini sudah disetujui.';
            return '';
        },

        // ── Kelola milestone ──
        async call(fn) {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try { await fn(); } catch (e) { this.error = e.message; } finally { this.busy = false; }
        },

        async add() {
            await this.call(async () => {
                const body = { title: this.draft.title, description: this.draft.description || null, due_date: this.draft.due_date || null };
                await api('POST', `/api/projects/${projectId}/milestones`, body);
                this.draft = { title: '', description: '', due_date: '' };
                this.adding = false;
                await this.reload();
                Alpine.store('toast').show('Milestone ditambahkan');
            });
        },

        startEdit(m) { this.editing = { id: m.id, title: m.title, description: m.description ?? '', due_date: m.due_date ?? '', locked: m.worked_on }; },

        async saveEdit() {
            await this.call(async () => {
                const e = this.editing;
                const body = { due_date: e.due_date || null };
                if (!e.locked) { body.title = e.title; body.description = e.description || null; }
                await api('PATCH', `/api/milestones/${e.id}`, body);
                this.editing = null;
                await this.reload();
                Alpine.store('toast').show('Milestone diperbarui');
            });
        },

        async remove(m) {
            if (!confirm(`Hapus milestone "${m.title}"?`)) return;
            await this.call(async () => {
                await api('DELETE', `/api/milestones/${m.id}`);
                await this.reload();
                Alpine.store('toast').show('Milestone dihapus');
            });
        },

        canMove(m, dir) {
            if (m.status !== 'planned') return false;
            const planned = this.milestones.filter((x) => x.status === 'planned');
            const i = planned.findIndex((x) => x.id === m.id);
            return dir < 0 ? i > 0 : i < planned.length - 1;
        },

        async move(m, dir) {
            const planned = this.milestones.filter((x) => x.status === 'planned');
            const i = planned.findIndex((x) => x.id === m.id);
            [planned[i], planned[i + dir]] = [planned[i + dir], planned[i]];
            await this.call(async () => {
                const res = await api('PATCH', `/api/projects/${projectId}/milestones/order`, { ids: planned.map((x) => x.id) });
                this.milestones = res.data;
            });
        },

        async reload() {
            const res = await api('GET', `/api/projects/${projectId}/milestones`);
            this.applyList(res.data, null);
        },

        // ── Update ──
        async toggle(m) {
            const opening = this.open !== m.id;
            this.open = opening ? m.id : null;
            this.formError = '';
            this.formErrors = {};
            if (opening) {
                this.form = { type: 'progress', percent: m.percent, note: '', title: '', links: '', files: [] };
                if (!this.updates[m.id]) await this.loadUpdates(m.id);
            }
        },

        async loadUpdates(id, older = false) {
            // Ambil kembali lewat `this` agar yang dimutasi adalah proxy reaktif, bukan objek mentah.
            if (!this.updates[id]) this.updates[id] = { items: [], hasMore: false, loading: false };
            const cur = this.updates[id];
            if (cur.loading) return;
            cur.loading = true;
            try {
                const q = older && cur.items.length ? `?before_id=${cur.items[cur.items.length - 1].id}` : '';
                const res = await api('GET', `/api/milestones/${id}/updates${q}`);
                cur.items = older ? [...cur.items, ...res.data] : res.data;
                cur.hasMore = res.meta.has_more;
            } catch (e) {
                this.error = e.message;
            } finally {
                cur.loading = false;
            }
        },

        pushUpdate(u) {
            const cur = this.updates[u.milestone_id];
            if (cur && !cur.items.some((x) => x.id === u.id)) cur.items.unshift(u);
        },

        pickFiles(ev) { this.form.files = Array.from(ev.target.files); },

        async submit(m) {
            this.formError = '';
            this.formErrors = {};
            const f = this.form;
            if (f.type === 'result' && !confirm('Serahkan milestone ini untuk direview klien? Setelah dikirim, milestone terkunci sampai klien merespons.')) return;
            if (this.busy) return;
            this.busy = true;
            try {
                const fd = new FormData();
                fd.append('type', f.type);
                fd.append('note', f.note);
                if (f.type === 'progress') fd.append('percent', f.percent);
                else fd.append('title', f.title);
                f.links.split('\n').map((l) => l.trim()).filter(Boolean).forEach((l) => fd.append('links[]', l));
                f.files.forEach((file) => fd.append('files[]', file));

                const res = await api('POST', `/api/milestones/${m.id}/updates`, fd);
                const i = this.milestones.findIndex((x) => x.id === m.id);
                if (i >= 0) this.milestones[i] = res.milestone;
                Object.assign(this.project, { status: res.project.status });
                this.pushUpdate(res.data);
                window.dispatchEvent(new CustomEvent('workspace-progress', { detail: res.project }));
                this.form = { type: 'progress', percent: res.milestone.percent, note: '', title: '', links: '', files: [] };
                if (this.$refs.files) this.$refs.files.value = '';
                Alpine.store('toast').show(f.type === 'result' ? 'Hasil diserahkan ke klien' : 'Progres dikirim');
            } catch (e) {
                this.formError = e.message;
                this.formErrors = e.errors ?? {};
            } finally {
                this.busy = false;
            }
        },

        errFor(key) {
            const hit = this.formErrors[key] ?? Object.entries(this.formErrors).find(([k]) => k.startsWith(key + '.'))?.[1];
            return hit?.[0] ?? '';
        },
    }));

    /**
     * Tab Chat klien–developer. Pesan terurut menurut `id` (bukan urutan kedatangan) dan disatukan
     * berdasarkan `id`, sehingga respons POST dan event realtime tidak menggandakan pesan.
     * `state`: ok | no_dev | closed. Reverb hanya penyampai; riwayat selalu dari API.
     */
    Alpine.data('chatBoard', (projectId, initial, hasMore, lastRead, meId, state, unread) => ({
        items: initial,
        hasMore,
        state,
        unread,
        active: false,
        connected: !window.Echo,
        offline: !!window.Echo,
        body: '',
        files: [],
        sending: false,
        failed: false,
        loading: false,
        error: '',
        newCount: 0,
        atBottom: true,
        dividerId: null,
        now: Date.now(),
        hiddenAt: 0,

        init() {
            // Pemisah "belum dibaca" ditetapkan saat halaman dibuka.
            this.dividerId = this.items.find((m) => m.id > lastRead && m.author.id !== meId)?.id ?? null;
            setInterval(() => { this.now = Date.now(); }, 30000);
            this.$watch('unread', (n) => window.dispatchEvent(new CustomEvent('workspace-chat-unread', { detail: n })));
            window.dispatchEvent(new CustomEvent('workspace-chat-unread', { detail: this.unread }));

            const channel = window.Echo?.private(`project.${projectId}`);
            channel?.listen('.message.created', (m) => this.push(m)).listen('.message.deleted', (e) => this.markDeleted(e.id));

            const conn = window.Echo?.connector?.pusher?.connection;
            if (conn) {
                this.connected = conn.state === 'connected';
                conn.bind('state_change', ({ current }) => {
                    const was = this.connected;
                    this.connected = current === 'connected';
                    if (this.connected && !was) this.recover();
                });
            }

            // Pulihkan pesan yang terlewat saat tab kembali fokus setelah > 30 detik.
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) this.hiddenAt = Date.now();
                else if (Date.now() - this.hiddenAt > 30000) this.recover();
            });
            window.addEventListener('focus', () => this.markRead());
            this.$watch('active', (on) => { if (on) { this.$nextTick(() => { this.scrollBottom(); this.markRead(); }); } });
        },

        get box() { return this.$refs.box; },
        get canSend() { return this.state === 'ok' && !this.sending && (this.body.trim().length > 0 || this.files.length > 0) && this.body.length <= 2000; },
        get lastId() { return this.items.length ? this.items[this.items.length - 1].id : 0; },

        // ── tampilan ──
        initials(name) { return name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase(); },
        time(iso) { return new Date(iso).toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', hour12: false }); },
        dayKey(iso) { return new Date(iso).toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' }); },
        dayLabel(iso) {
            const k = this.dayKey(iso);
            if (k === this.dayKey(new Date(this.now).toISOString())) return 'Hari ini';
            if (k === this.dayKey(new Date(this.now - 86400000).toISOString())) return 'Kemarin';
            return new Date(iso).toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', year: 'numeric' });
        },
        showDay(i) { return i === 0 || this.dayKey(this.items[i].created_at) !== this.dayKey(this.items[i - 1].created_at); },
        isImage(a) { return a.file_type.startsWith('image/'); },
        fileUrl(a) { return `/api/attachments/download/${a.id}`; },
        canDelete(m) { return !m.deleted && m.author.id === meId && (this.now - new Date(m.created_at).getTime()) < 5 * 60 * 1000; },
        fmtSize(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; },

        /** Teks polos di-escape, lalu URL http(s) dijadikan tautan aman; skema lain tetap teks (R5.5). */
        render(text) {
            const esc = (text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            return esc.replace(/\bhttps?:\/\/[^\s<]+/g, (url) => `<a href="${url}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold underline">${url}</a>`);
        },

        scrollBottom() { const b = this.box; if (b) { b.scrollTop = b.scrollHeight; this.atBottom = true; this.newCount = 0; } },
        onScroll() {
            const b = this.box;
            this.atBottom = b.scrollHeight - b.scrollTop - b.clientHeight < 24;
            if (this.atBottom) { this.newCount = 0; this.markRead(); }
        },

        // ── data ──
        push(m) {
            if (this.items.some((x) => x.id === m.id)) return;
            const at = this.items.findIndex((x) => x.id > m.id);
            if (at === -1) this.items.push(m); else this.items.splice(at, 0, m);
            if (m.author.id === meId) { this.$nextTick(() => this.scrollBottom()); return; }

            if (this.active && this.atBottom) { this.$nextTick(() => { this.scrollBottom(); this.markRead(); }); }
            else { this.unread++; if (this.active) this.newCount++; }
        },
        markDeleted(id) {
            const m = this.items.find((x) => x.id === id);
            if (m) { m.deleted = true; m.body = null; m.attachments = []; }
        },

        async recover() {
            try {
                const res = await api('GET', `/api/projects/${projectId}/messages?after_id=${this.lastId}`);
                res.data.forEach((m) => this.push(m));
            } catch { /* percobaan berikutnya saat koneksi/fokus kembali */ }
        },

        async markRead() {
            if (!this.active || !document.hasFocus() || !this.atBottom || !this.lastId || this.state === 'no_dev') return;
            if (this.unread === 0) return;
            try {
                const res = await api('POST', `/api/projects/${projectId}/messages/read`, { last_message_id: this.lastId });
                this.unread = res.data.unread;
                this.newCount = 0;
            } catch { /* abaikan; dicoba lagi saat fokus */ }
        },

        async loadOlder() {
            if (this.loading || !this.items.length) return;
            this.loading = true;
            const prev = this.box.scrollHeight;
            try {
                const res = await api('GET', `/api/projects/${projectId}/messages?before_id=${this.items[0].id}`);
                this.items = [...res.data.reverse(), ...this.items];
                this.hasMore = res.meta.has_more;
                this.$nextTick(() => { this.box.scrollTop = this.box.scrollHeight - prev; });
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        // ── kirim ──
        pickFiles(ev) {
            const picked = Array.from(ev.target.files);
            ev.target.value = '';
            this.files = [...this.files, ...picked].slice(0, 3);
            if (this.files.length + picked.length > 3 && picked.length) this.error = 'Maksimal 3 lampiran, masing-masing 10 MB (PNG, JPG, atau PDF).';
        },
        dropFile(i) { this.files.splice(i, 1); },
        onKey(ev) { if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) { ev.preventDefault(); this.send(); } },

        async send() {
            if (!this.canSend) return;
            this.sending = true;
            this.failed = false;
            this.error = '';
            try {
                const fd = new FormData();
                if (this.body.trim()) fd.append('body', this.body);
                this.files.forEach((f) => fd.append('files[]', f));
                const res = await api('POST', `/api/projects/${projectId}/messages`, fd);
                this.push(res.data);
                this.body = '';
                this.files = [];
                this.$refs.input?.focus();
            } catch (e) {
                // Teks dan lampiran tetap di kolom supaya bisa dikirim ulang atau disalin (R5.4).
                this.error = e.message;
                this.failed = true;
                if (e.status === 409) this.state = 'closed';
            } finally {
                this.sending = false;
            }
        },

        async remove(m) {
            if (!confirm('Hapus pesan ini?')) return;
            try {
                await api('DELETE', `/api/messages/${m.id}`);
                this.markDeleted(m.id);
            } catch (e) {
                this.error = e.message;
            }
        },
    }));

    /**
     * Tab Diskusi: komentar (terlama -> terbaru). `refs` = daftar hasil (update) untuk kaitan "Tentang".
     * Komentar baru/dihapus datang lewat Reverb.
     */
    Alpine.data('discussion', (projectId, initial, hasMore, meId, refs, milestones) => ({
        items: initial,
        hasMore,
        refs,
        milestones,
        filter: '',
        body: '',
        about: '',
        sending: false,
        loading: false,
        error: '',
        now: Date.now(),

        init() {
            setInterval(() => { this.now = Date.now(); }, 30000);
            window.Echo?.private(`project.${projectId}`)
                .listen('.comment.created', (c) => this.push(c))
                .listen('.comment.deleted', (e) => this.markDeleted(e.id))
                .listen('.update.created', (u) => { if (u.type === 'result') this.refs.push({ id: u.id, milestone_id: u.milestone_id, title: u.title }); })
                .listen('.milestone.changed', (e) => { this.milestones = e.milestones.map((m) => ({ id: m.id, title: m.title })); });
            this.$nextTick(() => this.scrollBottom());
        },

        get visible() {
            if (!this.filter) return this.items;
            const id = Number(this.filter);
            return this.items.filter((c) => c.update_id && this.refFor(c.update_id)?.milestone_id === id);
        },
        get left() { return 2000 - this.body.length; },
        get canSend() { return this.body.trim().length > 0 && this.left >= 0 && !this.sending; },

        refFor(id) { return this.refs.find((r) => r.id === id); },
        aboutLabel(c) {
            const r = this.refFor(c.update_id);
            const m = r && this.milestones.find((x) => x.id === r.milestone_id);
            return r ? `Tentang: ${m?.title ?? 'Milestone'} · ${r.title ?? 'Update'}` : 'Tentang: update';
        },
        canDelete(c) {
            return !c.deleted && c.author.id === meId && (this.now - new Date(c.created_at).getTime()) < 15 * 60 * 1000;
        },
        time(iso) {
            return new Date(iso).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false });
        },
        scrollBottom() { const b = this.$refs.box; if (b) b.scrollTop = b.scrollHeight; },

        push(c) {
            if (this.items.some((x) => x.id === c.id)) return;
            this.items.push(c);
            this.$nextTick(() => this.scrollBottom());
        },
        markDeleted(id) {
            const c = this.items.find((x) => x.id === id);
            if (c) { c.deleted = true; c.body = null; }
        },

        async send() {
            if (!this.canSend) return;
            this.sending = true;
            this.error = '';
            try {
                const res = await api('POST', `/api/projects/${projectId}/comments`, { body: this.body, update_id: this.about ? Number(this.about) : null });
                this.push(res.data);
                this.body = '';
            } catch (e) {
                this.error = e.message;
            } finally {
                this.sending = false;
            }
        },

        async remove(c) {
            if (!confirm('Hapus komentar ini?')) return;
            try {
                await api('DELETE', `/api/comments/${c.id}`);
                this.markDeleted(c.id);
            } catch (e) {
                this.error = e.message;
            }
        },

        async loadOlder() {
            if (this.loading || !this.items.length) return;
            this.loading = true;
            try {
                const res = await api('GET', `/api/projects/${projectId}/comments?before_id=${this.items[0].id}`);
                this.items = [...res.data.reverse(), ...this.items];
                this.hasMore = res.meta.has_more;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },
    }));
}
