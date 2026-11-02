<?php

namespace Weboldalnet\CommerceStripe\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceCore\Services\PaymentCallbackProcessor;

/**
 * A Stripe Checkout success_url-je: /commerce/stripe/return?session_id=cs_…
 *
 * A munkamenet állapotát a Stripe API-tól kérdezi le (a böngésző által hozott paraméter csak az azonosító), feldolgozza
 * a commerce-core-ral (PaymentSucceeded / … esemény, ha még nem történt meg a webhookból), majd a gazda-alkalmazás
 * eredményoldalára irányít (a munkamenet `return_url` metaadata — csak az alkalmazás saját hostjára).
 */
class StripeReturnController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments, PaymentCallbackProcessor $processor)
    {
        $code = config('commerce-stripe.provider_code', 'stripe');
        $returnUrl = null;

        try {
            $result = $payments->handleReturn($code, ['session_id' => (string) $request->query('session_id')]);
            $returnUrl = $result->rawPayload['return_url'] ?? null;
            $processor->processResult($code, $result, ['source' => 'return'] + (array) $result->rawPayload);
        } catch (\Throwable $e) {
            Log::error('Stripe visszatérés feldolgozási hiba: ' . $e->getMessage());
        }

        return redirect()->to($this->safeUrl($returnUrl));
    }

    /** Csak relatív cím vagy az alkalmazás saját hostja (nyitott átirányítás ellen). */
    private function safeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return url('/');
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $allowed = array_filter([request()->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);

        return $host && in_array(strtolower($host), array_map('strtolower', $allowed), true) ? $url : url('/');
    }
}
