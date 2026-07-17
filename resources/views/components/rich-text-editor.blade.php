{{--
    Wraps a Trix rich-text editor bound to a Livewire property via $wire —
    used for Admin\Articles\Form's per-locale `body` fields (one instance
    per locale tab). Trix manages its own contenteditable DOM tree that
    Livewire doesn't know about, so the whole thing is `wire:ignore`d:
    Livewire renders it once on mount and never morphs/touches this
    subtree again, relying entirely on the `trix-change` listener below to
    keep the underlying Livewire property in sync going forward — the
    standard pattern for embedding any stateful 3rd-party DOM widget
    inside a Livewire component (same class of problem as chart libraries
    or Select2).

    Image attachments (`trix-attachment-add`) upload via `$wire.upload()`
    against a single shared `editorImageUpload` property on the host
    component (see Admin\Articles\Form::storeEditorImage()) — fine for the
    realistic case of one attachment at a time per editor instance, not
    engineered for truly concurrent uploads across locale tabs.

    Inserted images get a small hover overlay to resize (S/M/L) and align
    them — Trix ships no UI of its own for either, only a bare Remove
    button, so both are added on top via Trix's public Attachment API
    (`attachment.setAttributes({...})`). Width/height/alt are the only
    attachment attributes Trix's own code (PreviewableAttachmentView.
    updateAttributesForImage()) actually applies to the live/output <img> —
    confirmed against the installed trix package's source, not assumed.
    A `style` attribute (float + margin, for text-wrap-around-image) isn't
    one of those: setAttributes({style}) updates Trix's own document model
    correctly, but Trix's *serializer* buries anything outside that
    width/height/alt allowlist inside the figure's own data-trix-attachment
    JSON — its internal round-trip format, not real CSS a browser or the
    storefront's raw-HTML article render would ever read. So this handles
    style on two separate tracks: hydrateStyles() re-applies it to the
    *live* img (editor view only, cosmetic) from the attachment model, and
    applyStylesToSerializedHtml() below fixes up the *saved* value itself
    by pulling style back out of that JSON and onto the <img> as a real
    attribute before syncing to Livewire — width/height need neither, Trix
    already writes those out as real attributes on both paths.

    The overlay is a single floating element positioned over the hovered
    attachment via getBoundingClientRect() — deliberately NOT injected as a
    child of the attachment's own <figure> (an earlier version did that,
    via a MutationObserver watching for new figures). Trix rebuilds a
    figure's DOM from scratch whenever the attachment's content-affecting
    attributes change — notably the pending -> uploaded transition, when
    storeEditorImage()'s url/href lands — and an observer reacting to that
    by re-injecting into the newly-rebuilt figure fought Trix's own
    re-render in a mutate/observe loop that froze the tab. Living outside
    Trix's managed subtree entirely sidesteps that class of bug altogether.
--}}
@props(['name', 'value' => '', 'error' => false])

@php
    $inputId = 'trix-input-'.str_replace('.', '-', $name);
@endphp

<div
    wire:ignore
    x-data="{
        init() {
            const editor = this.$refs.editor;
            const container = this.$el;

            const toolbar = document.createElement('div');
            toolbar.className = 'trix-resize-controls';
            toolbar.style.display = 'none';
            toolbar.setAttribute('contenteditable', 'false');
            let activeAttachmentId = null;
            let hideTimeout = null;

            const findActiveAttachmentAndImg = () => {
                if (activeAttachmentId === null) {
                    return {};
                }

                const attachment = editor.editor?.getDocument().getAttachmentById(activeAttachmentId);
                const figure = Array.from(editor.querySelectorAll('figure[data-trix-attachment]')).find((f) => parseInt(f.dataset.trixId, 10) === activeAttachmentId);

                return { attachment, img: figure?.querySelector('img') };
            };

            const setActiveWidth = (ratio) => {
                const { attachment, img } = findActiveAttachmentAndImg();
                const naturalWidth = img?.naturalWidth || img?.width;
                const naturalHeight = img?.naturalHeight || img?.height;

                if (! attachment || ! naturalWidth || ! naturalHeight) {
                    return;
                }

                // Percentage of the editor's own content width, never
                // upscaled past the image's real resolution.
                const width = Math.round(Math.min(naturalWidth, (editor.clientWidth || 640) * ratio));
                const height = Math.round(width * (naturalHeight / naturalWidth));

                attachment.setAttributes({ width, height });
            };

            const alignStyles = {
                left: 'float: left; margin: 0 1rem 0.5rem 0;',
                right: 'float: right; margin: 0 0 0.5rem 1rem;',
                none: '',
            };

            const setActiveAlign = (align) => {
                const { attachment, img } = findActiveAttachmentAndImg();

                if (! attachment) {
                    return;
                }

                const style = alignStyles[align] ?? '';
                attachment.setAttributes({ style });

                // Persistence goes through setAttributes above (it lands in
                // Trix's own document model, so it's there in the saved
                // value regardless of anything below) — but unlike
                // width/height, Trix's own image-refresh code doesn't apply
                // a `style` attribute change to the live img itself, so the
                // edit view wouldn't visually reflect it without this.
                if (img) {
                    img.setAttribute('style', style);
                }
            };

            const addButton = (label, title, onClick) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;
                button.title = title;
                // Keep Trix's own selection/cursor untouched by this click.
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    onClick();
                });
                toolbar.appendChild(button);
            };

            [['S', 0.25], ['M', 0.5], ['L', 1]].forEach(([label, ratio]) => {
                addButton(label, 'Resize', () => setActiveWidth(ratio));
            });

            const divider = document.createElement('span');
            divider.className = 'trix-resize-controls-divider';
            toolbar.appendChild(divider);

            addButton('⇤', 'Align left (text wraps right)', () => setActiveAlign('left'));
            addButton('⊘', 'No wrap', () => setActiveAlign('none'));
            addButton('⇥', 'Align right (text wraps left)', () => setActiveAlign('right'));

            container.style.position = container.style.position || 'relative';
            container.appendChild(toolbar);

            const showToolbarOver = (figure) => {
                const containerRect = container.getBoundingClientRect();
                const figureRect = figure.getBoundingClientRect();
                toolbar.style.display = 'flex';
                toolbar.style.top = (figureRect.top - containerRect.top + 6) + 'px';
                toolbar.style.left = (figureRect.right - containerRect.left - toolbar.offsetWidth - 6) + 'px';
            };

            const scheduleHide = () => {
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(() => {
                    toolbar.style.display = 'none';
                }, 150);
            };

            // Hit-tested on every mousemove (rather than figure-level
            // mouseenter/mouseleave) so hover tracking survives Trix
            // replacing a figure's DOM node out from under the cursor —
            // each move just re-checks whatever element is currently there.
            editor.addEventListener('mousemove', (event) => {
                const figure = event.target.closest('figure[data-trix-attachment]');

                if (figure) {
                    clearTimeout(hideTimeout);
                    activeAttachmentId = parseInt(figure.dataset.trixId, 10);
                    showToolbarOver(figure);
                }
            });
            editor.addEventListener('mouseleave', scheduleHide);
            toolbar.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
            toolbar.addEventListener('mouseleave', scheduleHide);

            // Trix's own parser/renderer doesn't apply a `style` attribute
            // to the live img the way it does width/height/alt (see the
            // component's docblock) — this re-applies it from each
            // attachment's own model data whenever there's a live img
            // missing it: once on initial load (an existing article with an
            // already-floated image, freshly parsed from saved HTML) and
            // again after every change (Trix rebuilds a figure's <img> from
            // scratch on the pending -> uploaded transition, which would
            // otherwise silently drop the visual float until next hover).
            const hydrateStyles = () => {
                editor.querySelectorAll('figure[data-trix-attachment]').forEach((figure) => {
                    const img = figure.querySelector('img');
                    const trixId = parseInt(figure.dataset.trixId, 10);
                    const attachment = editor.editor?.getDocument().getAttachmentById(trixId);
                    const style = attachment?.getAttribute('style');

                    if (img && style && ! img.hasAttribute('style')) {
                        img.setAttribute('style', style);
                    }
                });
            };

            // Separately from the live-view hydration above: Trix's own
            // *serialized* value (event.target.value, what's actually
            // saved) never puts an attachment's custom `style` attribute
            // onto the output <img> either — it only writes width/height/
            // alt there and buries everything else (style included) inside
            // the figure's own data-trix-attachment JSON, which is Trix's
            // internal round-trip format, not real CSS a browser or the
            // storefront's raw-HTML article render would ever apply. This
            // walks the serialized HTML string itself (a detached, inert
            // container — never touches the live editor DOM) pulling each
            // figure's style back out of that JSON and onto its <img> as a
            // real attribute before the value is saved.
            const applyStylesToSerializedHtml = (html) => {
                const container = document.createElement('div');
                container.innerHTML = html;

                container.querySelectorAll('figure[data-trix-attachment]').forEach((figure) => {
                    const raw = figure.getAttribute('data-trix-attachment');

                    if (! raw) {
                        return;
                    }

                    try {
                        const meta = JSON.parse(raw);
                        const img = figure.querySelector('img');

                        if (img && meta.style) {
                            img.setAttribute('style', meta.style);
                        }
                    } catch (e) {
                        // Malformed/missing JSON — leave this figure as-is.
                    }
                });

                return container.innerHTML;
            };

            editor.addEventListener('trix-initialize', hydrateStyles);

            editor.addEventListener('trix-change', (event) => {
                hydrateStyles();
                $wire.set('{{ $name }}', applyStylesToSerializedHtml(event.target.value), false);
            });

            editor.addEventListener('trix-attachment-add', (event) => {
                if (! event.attachment.file) {
                    return;
                }

                $wire.upload(
                    'editorImageUpload',
                    event.attachment.file,
                    () => {
                        $wire.call('storeEditorImage').then((url) => {
                            event.attachment.setAttributes({ url, href: url });
                        }).catch(() => {
                            event.attachment.remove();
                        });
                    },
                    () => {
                        event.attachment.remove();
                    },
                    (progressEvent) => {
                        event.attachment.setUploadProgress(progressEvent.detail.progress);
                    },
                );
            });
        },
    }"
>
    <input id="{{ $inputId }}" type="hidden" value="{{ $value }}">
    <trix-editor x-ref="editor" input="{{ $inputId }}" class="{{ $error ? '!border-danger-600' : '' }}"></trix-editor>
</div>
