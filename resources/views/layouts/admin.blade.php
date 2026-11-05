<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" href="/favicon.ico" sizes="any">

        <title>{{ isset($title) ? $title.' · Ribbon Admin' : 'Ribbon Admin' }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="panel-admin bg-surface text-text-primary text-sm antialiased">
        @php
            // This layout is only ever reached through the `admin.auth`
            // middleware (see EnsureAdminIsAuthenticated), which already
            // guarantees Auth::user() is set AND holds an admin role.
            $adminRole = Auth::user()->adminRoleOrFail();
        @endphp

        <x-panel.shell :tag="__('admin.ui.admin_tag')" :logout-label="__('admin.ui.log_out')" :open-label="__('admin.ui.open_navigation')" :close-label="__('admin.ui.close_navigation')" :user-name="Auth::user()->name" :user-sub="$adminRole->name" :breadcrumb="$breadcrumb ?? []">
            <x-slot:nav><x-admin.nav /></x-slot:nav>
            {{ $slot }}
        </x-panel.shell>

        @livewireScripts
    </body>
</html>
