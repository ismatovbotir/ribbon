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

];
