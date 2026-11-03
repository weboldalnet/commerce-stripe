<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bekapcsolás
    |--------------------------------------------------------------------------
    |
    | Kikapcsolva a provider nem regisztrálódik a commerce-core PaymentManagerbe.
    | Titkos kulcs nélkül sem: a fizetési módok között csak akkor jelenik meg, ha
    | ténylegesen használható.
    |
    */
    'enabled' => env('COMMERCE_STRIPE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Provider kód
    |--------------------------------------------------------------------------
    |
    | Ezzel a kóddal kerül a commerce-core-ba (payment_method, a tranzakciók
    | `provider` oszlopa). Webshopban és egyedi rendelési folyamatban is ez.
    |
    */
    'provider_code' => env('COMMERCE_STRIPE_PROVIDER_CODE', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Kulcsok
    |--------------------------------------------------------------------------
    |
    | A titkos kulcs (sk_test_… / sk_live_…, korlátozott: rk_…) dönti el a módot:
    | a teszt kulcs tesztfizetést indít. 1.1.0 óta az admin beállításoldalon
    | (/webshop/stripe/settings) is megadhatók — titkosítva az adatbázisba
    | kerülnek, és elsőbbséget kapnak; ami ott nincs megadva, arra ez a .env-érték
    | érvényes. A publikus kulcs a hosztolt Checkouthoz nem kell; a környezet-
    | ellenőrzéshez és beágyazott fizetéshez tartjuk meg.
    |
    | Ugyanígy (admin, hiányában .env): enabled, payment_method_label, locale,
    | payment_method_types, session_lifetime, webhook_secret.
    |
    */
    'secret_key' => env('COMMERCE_STRIPE_SECRET_KEY', ''),
    'publishable_key' => env('COMMERCE_STRIPE_PUBLISHABLE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook aláíró titok
    |--------------------------------------------------------------------------
    |
    | A Stripe Dashboard › Developers › Webhooks végpontjának „Signing secret”-je
    | (whsec_…). A végpont: https://<domain>/commerce/stripe/webhook, események:
    | checkout.session.completed, checkout.session.async_payment_succeeded,
    | checkout.session.async_payment_failed, checkout.session.expired.
    | Nélküle a webhook minden kérést elutasít (a visszatérő oldal és az
    | egyeztető parancs ettől még működik, mert közvetlenül a Stripe API-t kérdezi).
    |
    */
    'webhook_secret' => env('COMMERCE_STRIPE_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Megjelenés
    |--------------------------------------------------------------------------
    */
    'payment_method_label' => env('COMMERCE_STRIPE_LABEL', 'Bankkártyás fizetés (Stripe)'),

    // a Stripe Checkout felületének nyelve (pl. hu, en, de, auto)
    'locale' => env('COMMERCE_STRIPE_LOCALE', 'hu'),

    // ha üres, a Stripe a Dashboardon bekapcsolt fizetési módokat kínálja (kártya, Apple Pay, Google Pay …)
    'payment_method_types' => array_filter(explode(',', (string) env('COMMERCE_STRIPE_PAYMENT_METHOD_TYPES', ''))),

    /*
    |--------------------------------------------------------------------------
    | Checkout munkamenet élettartama (másodperc)
    |--------------------------------------------------------------------------
    |
    | A Stripe 30 perc és 24 óra közötti értéket fogad el; ennyi ideig fizethető
    | ki ugyanaz a munkamenet. Lejárat után checkout.session.expired → megszakítva.
    |
    */
    'session_lifetime' => (int) env('COMMERCE_STRIPE_SESSION_LIFETIME', 3600),

    /*
    |--------------------------------------------------------------------------
    | Webhook-cím (csak kijelzés)
    |--------------------------------------------------------------------------
    |
    | Az admin beállításoldal ezt mutatja a Stripe Dashboardhoz. Üresen az
    | app.url + /commerce/stripe/webhook.
    |
    */
    'webhook_url' => env('COMMERCE_STRIPE_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Egyeztetés
    |--------------------------------------------------------------------------
    |
    | A `commerce-stripe:reconcile` parancs a legfeljebb ennyi napos, még
    | függőben lévő Stripe-tranzakciókat kérdezi le újra (ha a webhook elveszett).
    |
    */
    'reconcile_days' => (int) env('COMMERCE_STRIPE_RECONCILE_DAYS', 2),
];
