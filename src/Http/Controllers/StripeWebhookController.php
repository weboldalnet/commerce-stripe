<?php

namespace Weboldalnet\CommerceStripe\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Exceptions\CallbackValidationException;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceCore\Services\PaymentCallbackProcessor;

/**
 * Stripe webhook: POST /commerce/stripe/webhook — a `web` csoporton KÍVÜL (nincs munkamenet, nincs CSRF: a Stripe
 * szervere nem tud tokent küldeni). A hitelesség a Stripe-Signature aláírás (webhook-titok).
 *
 * Válaszok: 200 = feldolgozva vagy szándékosan kihagyva (a Stripe nem küldi újra), 400 = érvénytelen aláírás / törzs,
 * 500 = feldolgozási hiba (a Stripe később újrapróbálja).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments, PaymentCallbackProcessor $processor)
    {
        $code = config('commerce-stripe.provider_code', 'stripe');

        try {
            $result = $payments->handleCallback($code, [
                'payload' => $request->getContent(),
                'signature' => (string) $request->header('Stripe-Signature'),
            ]);
        } catch (CallbackValidationException $e) {
            Log::warning('Stripe webhook elutasítva: ' . $e->getMessage());

            return response()->json(['received' => false, 'error' => 'invalid'], 400);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook hiba: ' . $e->getMessage());

            return response()->json(['received' => false], 500);
        }

        if ($result->status === null) {
            return response()->json(['received' => true, 'ignored' => true]);
        }

        try {
            $processed = $processor->processResult($code, $result, ['source' => 'webhook'] + (array) $result->rawPayload);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook feldolgozási hiba: ' . $e->getMessage(), ['session' => $result->providerTransactionId]);

            return response()->json(['received' => false], 500);
        }

        return response()->json(['received' => true, 'skipped' => $processed['skipped'] ?? false]);
    }
}
