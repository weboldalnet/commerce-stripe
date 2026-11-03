<?php

use Illuminate\Support\Facades\Route;
use Weboldalnet\CommerceStripe\Http\Controllers\Admin\StripeSettingController;
use Weboldalnet\CommerceStripe\Http\Controllers\StripeReturnController;
use Weboldalnet\CommerceStripe\Http\Controllers\StripeWebhookController;

// a vevő böngészője tér vissza ide a Stripe Checkoutból (munkamenet kell az eredményoldalhoz)
Route::middleware(['web'])->group(function () {
    Route::get('/commerce/stripe/return', StripeReturnController::class)->name('commerce.stripe.return');
});

// szerver-szerver webhook: a `web` csoporton KÍVÜL (nincs munkamenet, nincs CSRF) — a hitelesség a Stripe-aláírás
Route::post('/commerce/stripe/webhook', StripeWebhookController::class)->name('commerce.stripe.webhook');

// admin: beállításoldal (mentés + kapcsolatteszt) — a weboldalnet-core admin-domainjén és köztes rétegeivel,
// mint a commerce-barion (az `admin_share` adja az admin layout közös adatait)
Route::domain(function_exists('getAdminDomain') ? getAdminDomain() : null)
    ->middleware(['web', 'admin_share', 'auth:admin'])
    ->prefix('webshop/stripe')
    ->name('admin.webshop.stripe.')
    ->group(function () {
        Route::get('/settings', [StripeSettingController::class, 'index'])->name('settings');
        Route::post('/settings', [StripeSettingController::class, 'update'])->name('settings.update');
        Route::post('/test-connection', [StripeSettingController::class, 'testConnection'])->name('test-connection');
    });
