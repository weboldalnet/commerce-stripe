<?php

namespace Weboldalnet\CommerceStripe\Services;

use Stripe\StripeClient;
use Weboldalnet\CommerceCore\Exceptions\PaymentException;

/**
 * A Stripe-kliens egy helyen: a titkos kulcs az admin beállításból, hiányában a configból (.env); a mód a kulcs
 * előtagjából.
 */
class StripeClientFactory
{
    public static function secretKey(): string
    {
        return trim((string) StripeSettingsService::get('secret_key', ''));
    }

    public static function isConfigured(): bool
    {
        return (bool) preg_match('/^(sk|rk)_(test|live)_/', self::secretKey());
    }

    /** test | live | null (nincs vagy érvénytelen kulcs) */
    public static function mode(): ?string
    {
        if (!preg_match('/^(?:sk|rk)_(test|live)_/', self::secretKey(), $m)) {
            return null;
        }

        return $m[1];
    }

    /** A kulcs maszkolva (admin kijelzéshez): sk_test_…a1b2 */
    public static function maskedKey(?string $key = null): string
    {
        $key = trim((string) ($key ?? self::secretKey()));
        if ($key === '') {
            return '';
        }
        $prefix = preg_match('/^((?:sk|rk|pk|whsec)_(?:test_|live_)?)/', $key, $m) ? $m[1] : '';

        return $prefix . '…' . substr($key, -4);
    }

    public static function make(): StripeClient
    {
        if (!self::isConfigured()) {
            throw new PaymentException('A Stripe titkos kulcsa nincs beállítva (admin: Stripe beállítások, vagy COMMERCE_STRIPE_SECRET_KEY).');
        }

        return new StripeClient(['api_key' => self::secretKey()]);
    }

    /**
     * Összeg → a Stripe legkisebb egysége. A Stripe a HUF-ot kéttizedes pénznemként kezeli (fillér),
     * a nulla tizedesű pénznemeket (JPY, KRW …) egészként.
     */
    public static function toMinor($amount, string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true)
            ? (int) round((float) $amount)
            : (int) round((float) $amount * 100);
    }

    public static function fromMinor($minor, string $currency): float
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true)
            ? (float) $minor
            : round(((float) $minor) / 100, 2);
    }

    /** https://docs.stripe.com/currencies#zero-decimal */
    const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
}
