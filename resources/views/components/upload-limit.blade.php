{{-- Tells app.js the server's upload limit, so oversized files fail with a clear message. --}}
<meta name="upload-limit" content="{{ \App\Support\UploadLimit::bytes() }}" data-message="{{ __('validation.upload_too_large', ['size' => \App\Support\UploadLimit::label()]) }}">
