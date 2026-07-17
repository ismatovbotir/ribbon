// Registers the <trix-editor>/<trix-toolbar> custom elements globally as an
// import side effect — used by resources/views/components/rich-text-editor.blade.php
// (admin Article body field). No explicit API surface needed here; the
// component wires to it via native `trix-*` DOM events.
import 'trix';
