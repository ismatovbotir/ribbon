<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" href="/favicon.ico" sizes="any">

        <title>{{ isset($title) ? $title.' · Ribbon' : 'Ribbon' }}</title>

        <x-upload-limit />

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    {{--
        Minimal standalone shell for public, unauthenticated pages (no
        sidebar/topbar — none of the admin/seller shell chrome applies here
        since there's no logged-in actor). Scoped `.panel-seller` for the
        teal accent since this page is seller-facing, per design doc 01.
    --}}
    <body class="panel-seller flex min-h-screen flex-col items-center justify-center bg-surface-subtle px-4 py-10 text-sm text-text-primary antialiased">
        <div class="w-full max-w-lg">
            <div class="mb-6 flex items-center justify-center gap-3">
                <x-logo size="md" />

                <nav aria-label="Language" class="ml-1 flex items-center gap-1 text-xs">
                    @foreach (['uz' => 'UZ', 'ru' => 'RU', 'en' => 'EN'] as $locale => $label)
                        <a
                            href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
                            class="inline-flex min-h-11 items-center rounded-sm px-2 {{ app()->getLocale() === $locale ? 'font-semibold text-accent-600' : 'text-text-muted hover:text-text-secondary' }}"
                        >{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            <div class="rounded-md border border-border bg-surface-raised shadow-sm">
                {{ $slot }}
            </div>
        </div>

        @livewireScripts
    </body>
</html>
