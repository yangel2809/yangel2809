import { api } from './api';

/**
 * Pantalla "Hoy". La UI cambia al instante (optimista) y se sincroniza
 * en segundo plano. Por hábito hay a lo sumo una petición en vuelo: si
 * tocas varias veces, al terminar se envía solo el último estado deseado.
 */
export default (init) => ({
    date: init.date,
    urls: init.urls,
    habits: init.habits.map((h) => ({ ...h, draft: h.note ?? '', noteOpen: false, busy: false, want: h.done, server: h.done })),
    priorities: init.priorities,
    error: null,

    get done() {
        return this.habits.filter((h) => h.done).length;
    },

    toggle(h) {
        h.done = !h.done;
        h.want = h.done;
        navigator.vibrate?.(8);
        this.sync(h);
    },

    async sync(h) {
        if (h.busy) return;
        h.busy = true;
        try {
            while (h.want !== h.server) {
                const want = h.want;
                const r = await api(this.url('log', h.id), 'PUT', { date: this.date, completed: want });
                h.server = r.done;
                h.streak = r.streak;
                h.week_count = r.week_count;
            }
        } catch (e) {
            h.done = h.want = h.server;
            this.fail(e);
        } finally {
            h.busy = false;
        }
    },

    async saveNote(h) {
        try {
            const r = await api(this.url('log', h.id), 'PUT', { date: this.date, note: h.draft });
            h.note = r.note;
            h.draft = r.note ?? '';
            h.noteOpen = false;
        } catch (e) {
            this.fail(e);
        }
    },

    async mark(p, value) {
        const prev = p.completed;
        p.completed = p.completed === value ? null : value;
        navigator.vibrate?.(8);
        try {
            const r = await api(this.url('priority', p.id), 'PATCH', { completed: p.completed });
            p.completed = r.completed;
        } catch (e) {
            p.completed = prev;
            this.fail(e);
        }
    },

    url(kind, id) {
        return this.urls[kind].replace('__ID__', id);
    },

    fail(e) {
        this.error = e.message;
        clearTimeout(this._t);
        this._t = setTimeout(() => (this.error = null), 4000);
    },
});
