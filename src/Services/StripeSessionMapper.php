<?php

namespace Weboldalnet\CommerceStripe\Services;

use Weboldalnet\CommerceCore\Data\PaymentCallbackResult;
use Weboldalnet\CommerceCore\Status\PaymentStatus;

/**
 * Stripe Checkout Session → commerce-core PaymentCallbackResult.
 *
 * - payment_status = paid / no_payment_required → PAID
 * - status = expired → CANCELLED (a vevő nem fizetett a munkamenet élettartama alatt)
 * - minden más (open, vagy complete + unpaid = késleltetett fizetési mód folyamatban) → PENDING
 * A sikertelen késleltetett fizetést (async_payment_failed) a webhook adja FAILED-ként (failed()).
 */
class StripeSessionMapper
{
    public static function map($session, string $providerCode): PaymentCallbackResult
    {
        $session = self::toArray($session);
        $paymentStatus = $session['payment_status'] ?? null;
        $sessionStatus = $session['status'] ?? null;

        if (in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            $status = PaymentStatus::PAID;
        } elseif ($sessionStatus === 'expired') {
            $status = PaymentStatus::CANCELLED;
        } else {
            $status = PaymentStatus::PENDING;
        }

        return self::result($session, $providerCode, $status, $status === PaymentStatus::PAID);
    }

    /** checkout.session.async_payment_failed */
    public static function failed($session, string $providerCode): PaymentCallbackResult
    {
        return self::result(self::toArray($session), $providerCode, PaymentStatus::FAILED, false, 'A késleltetett fizetés sikertelen.');
    }

    private static function result(array $session, string $providerCode, string $status, bool $success, ?string $message = null): PaymentCallbackResult
    {
        $currency = strtoupper((string) ($session['currency'] ?? 'HUF'));
        $metadata = (array) ($session['metadata'] ?? []);

        return new PaymentCallbackResult([
            'success' => $success,
            'status' => $status,
            'provider' => $providerCode,
            'transaction_id' => $session['client_reference_id'] ?? ($metadata['order_number'] ?? null),
            'provider_transaction_id' => $session['id'] ?? null,
            'order_id' => $metadata['order_id'] ?? null,
            'amount' => isset($session['amount_total']) ? StripeClientFactory::fromMinor($session['amount_total'], $currency) : null,
            'currency' => $currency,
            'message' => $message ?? ('Stripe munkamenet: ' . ($session['status'] ?? '?') . ' / ' . ($session['payment_status'] ?? '?')),
            'raw_payload' => [
                'session_id' => $session['id'] ?? null,
                'status' => $session['status'] ?? null,
                'payment_status' => $session['payment_status'] ?? null,
                'payment_intent' => is_array($session['payment_intent'] ?? null) ? ($session['payment_intent']['id'] ?? null) : ($session['payment_intent'] ?? null),
                'amount_total' => $session['amount_total'] ?? null,
                'currency' => $currency,
                'return_url' => $metadata['return_url'] ?? null,
                'livemode' => $session['livemode'] ?? null,
            ],
        ]);
    }

    private static function toArray($session): array
    {
        if (is_object($session) && method_exists($session, 'toArray')) {
            return $session->toArray();
        }

        return (array) $session;
    }
}
