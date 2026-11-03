<?php

namespace Weboldalnet\CommerceStripe\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Weboldalnet\CommerceStripe\Models\StripeSetting;

/**
 * Stripe beállítások: az adatbázisban tárolt (adminból szerkesztett) érték az elsődleges, hiányában a config/.env
 * alapértelmezés érvényes — ugyanaz a minta, mint a commerce-barion BarionSettingsService-e.
 *
 * VISSZAFELÉ KOMPATIBILIS (1.0.x): a csak .env-ből konfigurált telepítések (pl. K&K Tudatos Könyvelés) változatlanul
 * működnek — amíg az adminban nincs mentve érték, vagy a tábla még nem létezik (migráció előtt), a config dönt.
 */
class StripeSettingsService
{
    protected static $cacheKey = 'commerce_stripe_settings';
    protected static $typeCacheKey = 'commerce_stripe_setting_types';

    /** Az admin beállításoldalon szerkeszthető kulcsok (a config kulcsaival azonos nevűek). */
    public static function viewKeys(): array
    {
        return [
            'enabled', 'payment_method_label',
            'secret_key', 'publishable_key', 'webhook_secret',
            'locale', 'payment_method_types', 'session_lifetime',
        ];
    }

    /** Titkosítva tárolandó kulcsok */
    public static function encryptedKeys(): array
    {
        return ['secret_key', 'webhook_secret'];
    }

    /** Logikai (kapcsoló) kulcsok */
    public static function booleanKeys(): array
    {
        return ['enabled'];
    }

    /** @var bool|null a beállítás-tábla létezése (kérésenként egyszer nézzük meg) */
    protected static $tableExists = null;

    /**
     * Létezik-e már a tábla (frissítés után, `migrate` előtt még nem)? A hiányzó táblára futó lekérdezés PostgreSQL-ben
     * egy épp nyitott tranzakciót is megszakítana (pl. a fizetés feldolgozása közben) — ezért előbb a katalógust
     * kérdezzük, ami nem dob hibát.
     */
    public static function tableExists(): bool
    {
        if (self::$tableExists === null) {
            try {
                self::$tableExists = \Illuminate\Support\Facades\Schema::hasTable((new StripeSetting())->getTable());
            } catch (\Throwable $e) {
                self::$tableExists = false;
            }
        }

        return self::$tableExists;
    }

    public static function all(): array
    {
        if (!self::tableExists()) {
            // a tábla még nem létezik (migráció előtt) — a config érvényes
            return [];
        }
        try {
            return Cache::rememberForever(self::$cacheKey, function () {
                return StripeSetting::query()->pluck('value', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Kulcs => típus térkép egyetlen lekérdezésből, cache-elve. */
    protected static function types(): array
    {
        if (!self::tableExists()) {
            return [];
        }
        try {
            return Cache::rememberForever(self::$typeCacheKey, function () {
                return StripeSetting::query()->pluck('type', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Van-e az adminban mentett (nem üres) értéke a kulcsnak? */
    public static function hasDbValue(string $key): bool
    {
        $settings = self::all();

        return array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '';
    }

    /** Honnan jön az érvényes érték: 'admin' | 'env' | null (sehol nincs megadva). */
    public static function source(string $key): ?string
    {
        if (self::hasDbValue($key)) {
            return 'admin';
        }
        $config = config('commerce-stripe.' . $key);

        return ($config !== null && $config !== '' && $config !== []) ? 'env' : null;
    }

    public static function get(string $key, $default = null)
    {
        $type = self::types()[$key] ?? null;

        if (!self::hasDbValue($key)) {
            $value = config('commerce-stripe.' . $key);

            return ($value !== null && $value !== '') ? $value : $default;
        }

        $value = self::all()[$key];

        // a titkosítás csak a DB-ben tárolt értékre vonatkozik, a config/.env értéke nyers
        if ($type === 'encrypted') {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                // más APP_KEY-jel titkosított érték (pl. adatbázis-import): nem használható — az admin figyelmeztet
                return $default;
            }
        }

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /** Mentett, de nem visszafejthető titkok (megváltozott APP_KEY) — az admin figyelmeztetéséhez. */
    public static function undecryptableKeys(): array
    {
        $broken = [];
        foreach (self::encryptedKeys() as $key) {
            if (!self::hasDbValue($key)) {
                continue;
            }
            try {
                Crypt::decryptString(self::all()[$key]);
            } catch (\Throwable $e) {
                $broken[] = $key;
            }
        }

        return $broken;
    }

    public static function save(string $key, $value, string $type = 'string', string $group = 'general'): void
    {
        if ($type === 'encrypted' && $value !== null && $value !== '') {
            $value = Crypt::encryptString((string) $value);
        }

        StripeSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group]
        );

        self::clearCache();
    }

    /**
     * A Stripe fizetőoldalon kínált módok: üres tömb = a Stripe Dashboardon bekapcsolt módok (ajánlott). Az adminban
     * vesszős listaként tároljuk, a configban tömb.
     */
    public static function paymentMethodTypes(): array
    {
        $value = self::get('payment_method_types', []);
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_filter(array_map('trim', (array) $value), fn ($item) => $item !== ''));
    }

    /** A Checkout munkamenet élettartama másodpercben (a Stripe 30 perc – 24 óra között fogad el). */
    public static function sessionLifetime(): int
    {
        return max(1800, min(86400, (int) self::get('session_lifetime', 3600)));
    }

    public static function clearCache(): void
    {
        Cache::forget(self::$cacheKey);
        Cache::forget(self::$typeCacheKey);
    }
}
