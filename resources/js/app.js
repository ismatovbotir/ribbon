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
