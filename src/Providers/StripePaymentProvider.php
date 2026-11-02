<?php

namespace Weboldalnet\CommerceStripe\Providers;

use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Weboldalnet\CommerceCore\Contracts\PaymentProviderInterface;
use Weboldalnet\CommerceCore\Data\PaymentCallbackResult;
use Weboldalnet\CommerceCore\Data\PaymentCreateResult;
use Weboldalnet\CommerceCore\Data\PaymentRefundData;
use Weboldalnet\CommerceCore\Data\PaymentRefundResult;
use Weboldalnet\CommerceCore\Data\PaymentRequestData;
use Weboldalnet\CommerceCore\Exceptions\CallbackValidationException;
use Weboldalnet\CommerceCore\Status\PaymentStatus;
use Weboldalnet\CommerceStripe\Services\StripeClientFactory;
use Weboldalnet\CommerceStripe\Services\StripeSessionMapper;

/**
 * Stripe Checkout (hosztolt fizetési oldal) a commerce-core-hoz.
 *
 * - createPayment(): Checkout Session; a vevő a Stripe oldalán fizet. A tranzakció `provider_transaction_id`-je a
 *   munkamenet azonosítója (cs_…), a `transaction_id` a rendelésszám.
 * - Visszatérés: a success_url a csomag /commerce/stripe/return végpontja ({CHECKOUT_SESSION_ID}-vel); az a Stripe
 *   API-tól kéri le a munkamenetet (nem a böngésző paramétereinek hisz), majd a gazda-alkalmazás oldalára irányít
 *   (`return_url`, a munkamenet metaadatában).
 * - Webhook (/commerce/stripe/webhook, web-csoport és CSRF nélkül): aláírás-ellenőrzés a webhook-titokkal.
 * - queryStatus(): egyeztetéshez (elveszett webhook) — `commerce-stripe:reconcile`.
 */
class StripePaymentProvider implements PaymentProviderInterface
{
    /** A webhook ezekre az eseményekre reagál, a többit nyugtázza és kihagyja. */
    const HANDLED_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];

    public function getCode()
    {
        return config('commerce-stripe.provider_code', 'stripe');
    }

    public function getName()
    {
        return (string) config('commerce-stripe.payment_method_label', 'Bankkártyás fizetés (Stripe)');
    }

    public function isOnline()
    {
        return true;
    }

    public function createPayment(PaymentRequestData $data)
    {
        try {
            $currency = strtoupper((string) ($data->currency ?: 'HUF'));
            $params = [
                'mode' => 'payment',
                'line_items' => $this->lineItems($data, $currency),
                'client_reference_id' => (string) ($data->orderNumber ?: $data->orderId),
                'metadata' => $this->metadata($data),
                'payment_intent_data' => [
                    'description' => 'Rendelés ' . ($data->orderNumber ?: $data->orderId),
                    'metadata' => array_intersect_key($this->metadata($data), array_flip(['order_id', 'order_number'])),
                ],
                'success_url' => $this->returnEndpoint() . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => (string) ($data->extra['cancel_url'] ?? $data->returnUrl ?? url('/')),
                'locale' => (string) config('commerce-stripe.locale', 'hu'),
                'expires_at' => time() + max(1800, min(86400, (int) config('commerce-stripe.session_lifetime', 3600))),
            ];
            if ($data->customerEmail) {
                $params['customer_email'] = (string) $data->customerEmail;
            }
            if ($types = (array) config('commerce-stripe.payment_method_types', [])) {
                $params['payment_method_types'] = array_values($types);
            }

            $session = StripeClientFactory::make()->checkout->sessions->create($params, [
                // ugyanaz a fizetési kísérlet ne nyisson két munkamenetet (dupla kattintás, újraküldés) — percen belül; egy
                // későbbi újrapróbálás (pl. lejárt munkamenet után) már újat kap
                'idempotency_key' => 'cs-' . sha1(json_encode([$params['client_reference_id'], $params['line_items'], $params['metadata'], intdiv(time(), 60)])),
            ]);

            return new PaymentCreateResult([
                'success' => true,
                'status' => PaymentStatus::PENDING,
                'provider' => $this->getCode(),
                'transaction_id' => $params['client_reference_id'],
                'provider_transaction_id' => $session->id,
                'redirect_url' => $session->url,
                'message' => 'Stripe fizetés elindítva.',
                'raw_response' => ['session_id' => $session->id, 'expires_at' => $session->expires_at, 'livemode' => $session->livemode],
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe: a fizetés indítása sikertelen — ' . $e->getMessage(), ['order' => $data->orderNumber]);

            return new PaymentCreateResult([
                'success' => false,
                'status' => PaymentStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => 'A Stripe-fizetés indítása sikertelen: ' . $e->getMessage(),
            ]);
        }
    }

    /** Visszatérés: ['session_id' => cs_…] → a munkamenet a Stripe API-ból. */
    public function handleReturn(array $payload)
    {
        $sessionId = (string) ($payload['session_id'] ?? '');
        if (!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId)) {
            throw new CallbackValidationException('Érvénytelen Stripe munkamenet-azonosító.');
        }

        return StripeSessionMapper::map(StripeClientFactory::make()->checkout->sessions->retrieve($sessionId), $this->getCode());
    }

    /**
     * Webhook: ['payload' => nyers törzs, 'signature' => Stripe-Signature fejléc]. Az aláírás a webhook-titokkal
     * ellenőrzött; nem kezelt eseménynél a válasz `status` = null (a vezérlő nyugtázza és kihagyja).
     */
    public function handleCallback(array $payload)
    {
        $secret = trim((string) config('commerce-stripe.webhook_secret', ''));
        if ($secret === '') {
            throw new CallbackValidationException('A Stripe webhook-titok nincs beállítva (COMMERCE_STRIPE_WEBHOOK_SECRET).');
        }

        try {
            $event = Webhook::constructEvent((string) ($payload['payload'] ?? ''), (string) ($payload['signature'] ?? ''), $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            throw new CallbackValidationException('Érvénytelen Stripe webhook-aláírás vagy -törzs: ' . $e->getMessage());
        }

        if (!in_array($event->type, self::HANDLED_EVENTS, true)) {
            return new PaymentCallbackResult(['success' => false, 'status' => null, 'provider' => $this->getCode(), 'message' => 'Nem kezelt esemény: ' . $event->type]);
        }

        $session = $event->data->object;

        return $event->type === 'checkout.session.async_payment_failed'
            ? StripeSessionMapper::failed($session, $this->getCode())
            : StripeSessionMapper::map($session, $this->getCode());
    }

    /** Egyeztetés: a tranzakció (vagy munkamenet-azonosító) aktuális állapota a Stripe-tól. */
    public function queryStatus($transaction)
    {
        $sessionId = is_object($transaction) ? ($transaction->provider_transaction_id ?? null) : $transaction;

        return $sessionId ? $this->handleReturn(['session_id' => $sessionId]) : null;
    }

    public function refund(PaymentRefundData $data)
    {
        try {
            $client = StripeClientFactory::make();
            $session = $client->checkout->sessions->retrieve((string) $data->providerTransactionId);
            if (empty($session->payment_intent)) {
                return new PaymentRefundResult(['success' => false, 'provider' => $this->getCode(), 'message' => 'A munkamenethez nincs fizetés.']);
            }
            $params = ['payment_intent' => $session->payment_intent];
            if ($data->amount !== null) {
                $params['amount'] = StripeClientFactory::toMinor($data->amount, (string) ($data->currency ?: $session->currency));
            }
            $refund = $client->refunds->create($params);

            return new PaymentRefundResult([
                'success' => in_array($refund->status, ['succeeded', 'pending'], true),
                'status' => $refund->status === 'succeeded' ? PaymentStatus::REFUNDED : PaymentStatus::PENDING,
                'provider' => $this->getCode(),
                'transaction_id' => $data->transactionId,
                'provider_transaction_id' => $refund->id,
                'amount' => StripeClientFactory::fromMinor($refund->amount, (string) $refund->currency),
                'currency' => strtoupper((string) $refund->currency),
                'message' => 'Stripe visszatérítés: ' . $refund->status,
                'raw_response' => ['refund_id' => $refund->id, 'status' => $refund->status],
            ]);
        } catch (\Throwable $e) {
            return new PaymentRefundResult(['success' => false, 'provider' => $this->getCode(), 'message' => 'Stripe visszatérítési hiba: ' . $e->getMessage()]);
        }
    }

    /**
     * A tételek a rendelés BRUTTÓ egységáraival (a Stripe a végösszeget szedi be, adót nem számol). Ha a tételek
     * összege nem egyezik a fizetendővel (kerekítés, kedvezmény), egyetlen „Rendelés …” sor megy a végösszeggel.
     */
    private function lineItems(PaymentRequestData $data, string $currency): array
    {
        $lines = [];
        $sum = 0;
        foreach ((array) $data->items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unit = StripeClientFactory::toMinor($item['unit_price'] ?? (($item['total_price'] ?? 0) / $quantity), $currency);
            $product = ['name' => mb_substr((string) ($item['name'] ?? 'Tétel'), 0, 250)];
            if (!empty($item['description'])) {
                $product['description'] = mb_substr((string) $item['description'], 0, 500);
            }
            $lines[] = ['price_data' => ['currency' => strtolower($currency), 'unit_amount' => $unit, 'product_data' => $product], 'quantity' => $quantity];
            $sum += $unit * $quantity;
        }

        $total = StripeClientFactory::toMinor($data->amount, $currency);
        if (!$lines || $sum !== $total) {
            $lines = [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $total,
                    'product_data' => ['name' => 'Rendelés ' . ($data->orderNumber ?: $data->orderId)],
                ],
                'quantity' => 1,
            ]];
        }

        return $lines;
    }

    private function metadata(PaymentRequestData $data): array
    {
        return array_filter([
            'order_id' => $data->orderId !== null ? (string) $data->orderId : null,
            'order_number' => $data->orderNumber !== null ? (string) $data->orderNumber : null,
            // a csomag visszatérő végpontja ide irányít a feldolgozás után (a gazda-alkalmazás eredményoldala)
            'return_url' => $data->returnUrl ? mb_substr((string) $data->returnUrl, 0, 500) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function returnEndpoint(): string
    {
        return route('commerce.stripe.return');
    }
}
