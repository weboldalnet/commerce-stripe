<?php

namespace Weboldalnet\CommerceStripe;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceStripe\Console\StripeReconcileCommand;
use Weboldalnet\CommerceStripe\Providers\StripePaymentProvider;
use Weboldalnet\CommerceStripe\Services\StripeClientFactory;
use Weboldalnet\CommerceStripe\Services\StripeSettingsService;
use Weboldalnet\CommerceStripe\Support\PackageHelper;

class CommerceStripeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // a beállítás-tábla migrációja (a csomag maga tölti be, mint a commerce-barion)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        try {
            $paymentManager = $this->app->make(PaymentManager::class);
            $code = config('commerce-stripe.provider_code', 'stripe');

            $paymentManager->registerAvailable($code, [
                'name' => (string) StripeSettingsService::get('payment_method_label', 'Bankkártyás fizetés (Stripe)'),
                'settings_url' => '/webshop/stripe/settings',
                'settings_label' => 'Stripe',
                'online' => true,
            ]);

            // csak bekapcsolva ÉS kulccsal: kulcs nélkül ne kínáljuk fizetési módként (admin beállítás, hiányában .env)
            if (StripeSettingsService::getBool('enabled', true) && StripeClientFactory::isConfigured()) {
                $paymentManager->register($code, $this->app->make(StripePaymentProvider::class));
            }
        } catch (\Throwable $e) {
            Log::error('Stripe regisztrációs hiba: ' . $e->getMessage());
        }

        $publishList = [];
        foreach (PackageHelper::PACKAGE_LIST as $name => $publish) {
            $this->publishes([
                $publish['source'] => base_path($publish['destination']),
            ], PackageHelper::PACKAGE_PREFIX . '-' . $name);
            $publishList[$publish['source']] = base_path($publish['destination']);
        }
        $this->publishes($publishList, PackageHelper::PACKAGE_PREFIX . '-all');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/commerce-stripe.php', 'commerce-stripe');

        $this->app->singleton(StripePaymentProvider::class, fn () => new StripePaymentProvider());

        $this->commands([
            StripeReconcileCommand::class,
        ]);
    }
}
