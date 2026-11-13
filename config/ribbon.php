<?php

return [

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    // Locales required for admin-authored translatable content (categories,
    // category parameters, banners, CMS pages). Seller-entered free text
    // (e.g. a product's brand/model field) is a single plain string, not
    // translated. First locale is the default/fallback.
    'locales' => ['uz', 'ru', 'en'],

    // Options (and their order) for the seller-facing "preferred language"
    // select (owner registration + employee management) — this is a
    // per-user `users.locale` account preference, distinct from the
    // storefront's session-based locale switcher, and deliberately ordered
    // uz/en/ru rather than matching 'locales' above. Single source of truth
    // so the registration form and the employee-management form can't drift
    // out of sync with each other.
    'user_locales' => ['uz', 'en', 'ru'],

    // Reverse proxies whose X-Forwarded-For header may be trusted, so
    // request()->ip() is the real visitor (rate limits on login, /request,
    // "Show phone", selection; lead dedupe). Empty = trust nobody, which is
    // correct when visitors connect straight to the web server. Production is
    // shared hosting (webname.uz): leave empty unless every visitor shows the
    // same IP, then set the host's proxy IP(s), comma-separated. Never "*" on
    // a server reachable directly — clients could then forge their IP.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Admin staff must pass TOTP. Only ever turn this off on a local machine.
    'admin_2fa_required' => (bool) env('ADMIN_2FA_REQUIRED', true),

    // One-click demo logins on /login (Auth\Login::devLogin). Works only when
    // APP_ENV=local AND this is true, so one wrong APP_ENV on the shared
    // host can't open a passwordless login. Never set it on production.
    'dev_login' => (bool) env('DEV_LOGIN', false),

    // Claude Desktop MCP (routes/ai.php, local/SSH only): the Super Admin
    // user every MCP action is done as. Empty = every MCP tool refuses.
    'mcp_admin_email' => env('MCP_ADMIN_EMAIL'),
];
