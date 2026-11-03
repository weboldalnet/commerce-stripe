<?php

namespace Weboldalnet\CommerceStripe\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceStripe\Providers\StripePaymentProvider;
use Weboldalnet\CommerceStripe\Services\StripeClientFactory;
use Weboldalnet\CommerceStripe\Services\StripeSettingsService;
use Weboldalnet\CommerceStripe\Support\PackageHelper;

/**
 * Stripe beállításoldal (a commerce-barion beállításoldalának felépítésében).
 *
 * Az adminban mentett érték az elsődleges, hiányában a .env (COMMERCE_STRIPE_*) érvényes — így a csak .env-ből
 * beállított telepítések változatlanul működnek. A titkos kulcs és a webhook-titok titkosítva tárolódik, és soha nem
 * kerül vissza az oldalra (csak maszkolva: előtag + utolsó 4 karakter); üresen hagyva megmarad, a „törlés” jelölővel
 * az adminban mentett érték törölhető (ekkor újra a .env érvényes).
 */
class StripeSettingController extends Controller
{
    /** A fizetőoldal nyelve (Stripe Checkout `locale`) */
    const LOCALES = [
        'hu' => 'Magyar', 'en' => 'Angol', 'de' => 'Német', 'sk' => 'Szlovák', 'cs' => 'Cseh', 'ro' => 'Román',
        'hr' => 'Horvát', 'sl' => 'Szlovén', 'fr' => 'Francia', 'es' => 'Spanyol', 'it' => 'Olasz', 'pl' => 'Lengyel',
        'auto' => 'A böngésző nyelve',
    ];

    /** A Checkout munkamenet élettartama (mp) — a Stripe 30 perc és 24 óra között fogad el */
    const SESSION_LIFETIMES = [
        1800 => '30 perc', 3600 => '1 óra', 7200 => '2 óra', 21600 => '6 óra', 43200 => '12 óra', 86400 => '24 óra',
    ];

    /** Fizetési módok: üres = a Stripe Dashboardon bekapcsoltak; 'card' = csak bankkártya */
    const PAYMENT_METHOD_MODES = [
        '' => 'A Stripe Dashboardon bekapcsolt módok (ajánlott)',
        'card' => 'Csak bankkártya',
    ];

    public function index(PaymentManager $payments)
    {
        $code = config('commerce-stripe.provider_code', 'stripe');
        $siteBase = $this->siteBase();

        // FIGYELEM: a változó neve nem lehet $settings — a platform admin layoutja egy globálisan megosztott $settings
        // modellt használ, azt felülírnánk.
        $stripeSettings = [
            'enabled' => StripeSettingsService::getBool('enabled', true),
            'payment_method_label' => (string) StripeSettingsService::get('payment_method_label', ''),
            'publishable_key' => (string) StripeSettingsService::get('publishable_key', ''),
            'locale' => (string) StripeSettingsService::get('locale', 'hu'),
            'payment_method_types' => implode(',', StripeSettingsService::paymentMethodTypes()),
            'session_lifetime' => StripeSettingsService::sessionLifetime(),
        ];

        $secrets = [];
        foreach (StripeSettingsService::encryptedKeys() as $key) {
            $value = (string) StripeSettingsService::get($key, '');
            $secrets[$key] = [
                'masked' => StripeClientFactory::maskedKey($value),
                'source' => $value !== '' ? StripeSettingsService::source($key) : null,
            ];
        }

        return view(PackageHelper::PACKAGE_PREFIX . '::admin.settings', [
            'stripeSettings' => $stripeSettings,
            'stripeSecrets' => $secrets,
            // frissítés után a `migrate`-ig nincs tábla: addig csak a .env érvényes, az admin nem ment
            'storageReady' => StripeSettingsService::tableExists(),
            'publishableSource' => StripeSettingsService::source('publishable_key'),
            'registered' => $payments->hasProvider($code),
            'code' => $code,
            'mode' => StripeClientFactory::mode(),
            'publishableMode' => $this->keyMode($stripeSettings['publishable_key']),
            'undecryptable' => StripeSettingsService::undecryptableKeys(),
            'webhookUrl' => trim((string) config('commerce-stripe.webhook_url', '')) ?: $siteBase . '/commerce/stripe/webhook',
            'returnUrl' => $siteBase . '/commerce/stripe/return',
            'events' => StripePaymentProvider::HANDLED_EVENTS,
            'locales' => self::LOCALES,
            'sessionLifetimes' => self::SESSION_LIFETIMES,
            'paymentMethodModes' => self::PAYMENT_METHOD_MODES,
        ]);
    }

    public function update(Request $request)
    {
        if (!StripeSettingsService::tableExists()) {
            return redirect()->back()->with('error', 'A Stripe beállítások táblája még nincs létrehozva: futtasd a php artisan migrate parancsot. Addig a .env beállításai érvényesek.');
        }

        $request->validate([
            'payment_method_label' => 'nullable|string|max:120',
            'publishable_key' => ['nullable', 'regex:/^pk_(test|live)_[A-Za-z0-9]+$/'],
            'secret_key' => ['nullable', 'regex:/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/'],
            'webhook_secret' => ['nullable', 'regex:/^whsec_[A-Za-z0-9]+$/'],
            'locale' => 'required|in:' . implode(',', array_keys(self::LOCALES)),
            'session_lifetime' => 'required|in:' . implode(',', array_keys(self::SESSION_LIFETIMES)),
            'payment_method_types' => 'nullable|in:' . implode(',', array_filter(array_keys(self::PAYMENT_METHOD_MODES))),
        ], [
            'publishable_key.regex' => 'A publikus kulcs formája: pk_test_… vagy pk_live_… (szóköz nélkül).',
            'secret_key.regex' => 'A titkos kulcs formája: sk_test_… / rk_test_… vagy sk_live_… / rk_live_… (szóköz nélkül).',
            'webhook_secret.regex' => 'A webhook aláíró titok formája: whsec_… (szóköz nélkül).',
        ]);

        // a titok üresen hagyva megmarad, a „törlés” jelölővel az adminban mentett érték törlődik (.env érvényes)
        $newSecrets = [];
        foreach (StripeSettingsService::encryptedKeys() as $key) {
            $input = trim((string) $request->input($key, ''));
            if ($input !== '') {
                $newSecrets[$key] = $input;
            } elseif ($request->boolean('clear_' . $key)) {
                $newSecrets[$key] = '';
            }
        }

        // a publikus és a titkos kulcs ugyanabból a környezetből való legyen (a mentés után érvényes értékekkel)
        $secretMode = array_key_exists('secret_key', $newSecrets)
            ? $this->keyMode($newSecrets['secret_key'] !== '' ? $newSecrets['secret_key'] : (string) config('commerce-stripe.secret_key', ''))
            : StripeClientFactory::mode();
        $publishable = trim((string) $request->input('publishable_key', ''));
        $publishableMode = $this->keyMode($publishable !== '' ? $publishable : (string) config('commerce-stripe.publishable_key', ''));
        if ($secretMode && $publishableMode && $secretMode !== $publishableMode) {
            return redirect()->back()->withInput($request->except(StripeSettingsService::encryptedKeys()))
                ->withErrors(['publishable_key' => 'A publikus és a titkos kulcs nem ugyanabból a környezetből való (teszt / éles).']);
        }

        StripeSettingsService::save('enabled', $request->boolean('enabled'), 'boolean');
        StripeSettingsService::save('payment_method_label', trim((string) $request->input('payment_method_label', '')));
        StripeSettingsService::save('publishable_key', $publishable);
        StripeSettingsService::save('locale', (string) $request->input('locale'));
        StripeSettingsService::save('session_lifetime', (string) (int) $request->input('session_lifetime'));
        StripeSettingsService::save('payment_method_types', (string) $request->input('payment_method_types', ''));
        foreach ($newSecrets as $key => $value) {
            StripeSettingsService::save($key, $value, 'encrypted');
        }

        return redirect()->back()->with('success', 'Stripe beállítások sikeresen mentve.');
    }

    /**
     * Kapcsolatteszt: egy olvasó hívás a mentett kulccsal. A Checkout-munkamenetek listázása a korlátozott (csak
     * „Checkout Sessions” jogú) kulccsal is működik — a fizetéshez más jog nem kell.
     */
    public function testConnection()
    {
        try {
            $client = StripeClientFactory::make();
            $client->checkout->sessions->all(['limit' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Kapcsolat rendben (' . (StripeClientFactory::mode() === 'live' ? 'ÉLES' : 'teszt') . ' kulcs).',
            ]);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return response()->json(['success' => false, 'message' => 'A Stripe nem fogadta el a titkos kulcsot (hibás, visszavont vagy lejárt).']);
        } catch (\Stripe\Exception\PermissionException $e) {
            return response()->json(['success' => false, 'message' => 'A kulcsnak nincs joga a Checkout-munkamenetekhez (Checkout Sessions: Write kell).']);
        } catch (\Throwable $e) {
            // a Stripe hibaüzenete a kulcs egy részét is tartalmazhatja — nem írjuk ki szó szerint
            return response()->json(['success' => false, 'message' => 'Hiba a kapcsolódáskor: ' . class_basename($e) . '.']);
        }
    }

    /** test | live | null a kulcs előtagjából */
    private function keyMode(string $key): ?string
    {
        return preg_match('/^(?:sk|rk|pk)_(test|live)_/', trim($key), $m) ? $m[1] : null;
    }

    /**
     * A webhook és a visszatérés a SITE domainre érkezik (a route() itt az admin domaint adná). A portot a jelenlegi
     * kérésből vesszük át (fejlesztői szerver), ha az nem az alapértelmezett — mint a commerce-barion.
     */
    private function siteBase(): string
    {
        if (!function_exists('getSiteDomain')) {
            return rtrim((string) config('app.url'), '/');
        }
        $scheme = request()->getScheme();
        $host = getSiteDomain();
        $port = request()->getPort();
        $defaultPort = $scheme === 'https' ? 443 : 80;

        return $scheme . '://' . $host . (($port && (int) $port !== $defaultPort && !str_contains($host, ':')) ? ':' . $port : '');
    }
}
