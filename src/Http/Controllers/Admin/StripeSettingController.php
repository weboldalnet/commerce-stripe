<?php

namespace Weboldalnet\CommerceStripe\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceStripe\Services\StripeClientFactory;
use Weboldalnet\CommerceStripe\Support\PackageHelper;

/**
 * Stripe beállításoldal: CSAK kijelzés és kapcsolatteszt. A kulcsok a .env-ben élnek (maszkolva látszanak), az adminból
 * nem írhatók — így titkos kulcs nem kerül adatbázisba, és nem módosítható a felületről.
 */
class StripeSettingController extends Controller
{
    public function index(PaymentManager $payments)
    {
        $code = config('commerce-stripe.provider_code', 'stripe');
        $webhookUrl = trim((string) config('commerce-stripe.webhook_url', '')) ?: rtrim((string) config('app.url'), '/') . '/commerce/stripe/webhook';

        return view(PackageHelper::PACKAGE_PREFIX . '::admin.settings', [
            'enabled' => (bool) config('commerce-stripe.enabled', true),
            'registered' => $payments->hasProvider($code),
            'code' => $code,
            'mode' => StripeClientFactory::mode(),
            'maskedSecret' => StripeClientFactory::maskedKey(),
            'maskedWebhook' => StripeClientFactory::maskedKey((string) config('commerce-stripe.webhook_secret', '')),
            'webhookUrl' => $webhookUrl,
            'label' => (string) config('commerce-stripe.payment_method_label'),
            'locale' => (string) config('commerce-stripe.locale'),
            'events' => \Weboldalnet\CommerceStripe\Providers\StripePaymentProvider::HANDLED_EVENTS,
        ]);
    }

    /** Kapcsolatteszt: a fiók egyenlegének lekérése (csak olvasás). */
    public function testConnection()
    {
        try {
            $balance = StripeClientFactory::make()->balance->retrieve();

            return response()->json([
                'success' => true,
                'message' => 'Kapcsolat rendben (' . (StripeClientFactory::mode() === 'live' ? 'ÉLES' : 'teszt') . ' mód, '
                    . ($balance->livemode ? 'éles' : 'teszt') . ' fiók).',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Hiba a kapcsolódáskor: ' . $e->getMessage()]);
        }
    }
}
