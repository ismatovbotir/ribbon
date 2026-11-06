// Registers the <trix-editor>/<trix-toolbar> custom elements globally as an
// import side effect — used by resources/views/components/rich-text-editor.blade.php
// (admin Article body field). No explicit API surface needed here; the
// component wires to it via native `trix-*` DOM events.
import 'trix';

// Storefront catalog mega menu (spec 19 section 3.4). Alpine ships with Livewire,
// so we only register a component on its init event (no second Alpine).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('megaMenu', (startId = null) => ({
        open: false,
        pinned: false,
        timer: null,
        // Two-pane state (spec 22): the category whose pane is shown.
        active: startId,
        catTimer: null,
        last: null,
        prev: null,
        // "Menu aim": remember the last two pointer positions over the list
        // so a diagonal move toward the pane doesn't switch categories.
        track(e) {
            this.prev = this.last;
            this.last = { x: e.clientX, y: e.clientY };
        },
        aimingAtPane() {
            if (!this.prev || !this.last) return false;
            const dx = this.last.x - this.prev.x;
            const dy = Math.abs(this.last.y - this.prev.y);
            return dx > 0 && dx >= dy * 0.6;
        },
        hoverCat(id, e) {
            if (e.pointerType !== 'mouse') return;
            clearTimeout(this.catTimer);
            if (this.active === id) return;
            const delay = this.aimingAtPane() ? 300 : 120;
            this.catTimer = setTimeout(() => {
                // Still over that row? (left it during an aim → keep current pane)
                const row = this.$refs.list.querySelector(`[data-cat="${id}"]`);
                if (row && row.matches(':hover')) this.active = id;
            }, delay);
        },
        rows() {
            return [...this.$refs.list.querySelectorAll('a[data-cat]')];
        },
        moveFocus(step) {
            const rows = this.rows();
            if (!rows.length) return;
            let i = rows.indexOf(document.activeElement);
            if (step === 'first') i = 0;
            else if (step === 'last') i = rows.length - 1;
            else i = i < 0 ? 0 : (i + step + rows.length) % rows.length;
            rows[i].focus();
        },
        enterPane() {
            const pane = document.getElementById(`mega-pane-${this.active}`);
            pane?.querySelector('a')?.focus();
        },
        leavePane() {
            this.$refs.list.querySelector(`[data-cat="${this.active}"]`)?.focus();
        },
        hoverOpen(e) {
            if (e.pointerType !== 'mouse') return;
            clearTimeout(this.timer);
            if (this.open) return;
            this.timer = setTimeout(() => {
                this.open = true;
                this.pinned = false;
            }, 120);
        },
        hoverClose(e) {
            if (e.pointerType !== 'mouse') return;
            clearTimeout(this.timer);
            if (this.pinned) return;
            this.timer = setTimeout(() => {
                this.open = false;
            }, 200);
        },
        toggle() {
            clearTimeout(this.timer);
            if (this.open && this.pinned) return this.close();
            this.open = true;
            this.pinned = true;
        },
        focusFirst() {
            this.open = true;
            this.pinned = true;
            this.$nextTick(() => (this.$refs.list.querySelector(`[data-cat="${this.active}"]`) ?? this.rows()[0])?.focus());
        },
        focusOut(e) {
            if (e.relatedTarget && !this.$root.contains(e.relatedTarget)) this.close();
        },
        close() {
            clearTimeout(this.timer);
            clearTimeout(this.catTimer);
            const hadFocus = this.$root.contains(document.activeElement) && document.activeElement !== this.$refs.trigger;
            this.open = false;
            this.pinned = false;
            if (hadFocus) this.$refs.trigger.focus({ preventScroll: true });
        },
    }));
});

// Remembers what opened a modal so x-modal can return focus to it on close
// (QA N3-07). Clicks inside a menu are skipped: the item is removed with the
// menu, so the menu's trigger, clicked just before, stays the activator.
document.addEventListener(
    'click',
    (e) => {
        if (e.target.closest('[role="menu"]')) return;
        const activator = e.target.closest('button, a, [tabindex]');
        if (activator) window.ribbonLastActivator = activator;
    },
    true,
);

// A Livewire action can remove or disable the focused control (row delete,
// wire:loading.attr="disabled"), which drops keyboard users back to <body>.
// Put focus back on that control when it survives, else on the open dialog,
// else on <main> (QA N4-12). The control is tracked by focusin because
// wire:loading has already blurred it by the time the commit hook runs; a
// pointer press clears it, so clicking empty space isn't undone by a later commit.
let lastFocused = null;
document.addEventListener('focusin', (e) => (lastFocused = e.target));
document.addEventListener('pointerdown', () => (lastFocused = null), true);

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        const before = lastFocused;
        if (!before) return;

        succeed(() => {
            // After the morph, wire:loading cleanup and x-modal's own focus return.
            setTimeout(() => {
                if (document.activeElement !== document.body) return;
                // The shell's closed nav drawer is also aria-modal, so require visibility.
                const dialog = [...document.querySelectorAll('[aria-modal="true"]')].find((d) => d.getClientRects().length);
                const target =
                    before.isConnected && !before.disabled
                        ? before
                        : (dialog?.querySelector('button, a, input, select, textarea') ?? document.getElementById('main'));
                target?.focus({ preventScroll: true });
            }, 50);
        });
    });
});

// PHP silently discards a file above its upload limit, and Livewire then never
// reports finish or error, leaving "Uploading..." on forever (QA N5-02). Refuse
// such a file before it is sent. The limit comes from <x-upload-limit>.
const uploadLimit = () => {
    const meta = document.querySelector('meta[name="upload-limit"]');
    return meta ? { bytes: Number(meta.content), message: meta.dataset.message } : null;
};

document.addEventListener(
    'change',
    (e) => {
        const input = e.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;

        input.parentElement?.querySelector('[data-upload-limit-error]')?.remove();

        const limit = uploadLimit();
        if (!limit || ![...(input.files ?? [])].some((file) => file.size > limit.bytes)) return;

        // Capture phase: Livewire's own listener on the input never sees this.
        e.stopImmediatePropagation();
        input.value = '';

        const error = document.createElement('p');
        error.dataset.uploadLimitError = '';
        error.setAttribute('role', 'alert');
        error.className = 'mt-1 text-xs text-danger-600';
        error.textContent = limit.message;
        input.after(error);
    },
    true,
);
