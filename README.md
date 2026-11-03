# Stripe fizetési provider a commerce-core-hoz

Stripe **Checkout** (hosztolt fizetési oldal) a `weboldalnet/commerce-core` alapú rendszerekhez — webshopban
(`webshop-*` csomagok) és egyedi rendelési folyamatban (pl. egy termékoldal saját rendeléseivel) egyaránt.
A felépítése a `commerce-barion` csomagé.

## Telepítés

```bash
composer require weboldalnet/commerce-stripe
```

A `stripe/stripe-php` SDK függőségként érkezik. A Service Provider auto-discoveryvel töltődik.

Követelmény: `weboldalnet/commerce-core` **1.0.8** vagy újabb (`PaymentManager::registerAvailable()` 1.0.7 óta,
`PaymentCallbackProcessor::processResult()` 1.0.8 óta). A gazda-projekt `composer.json`-jában a csomag forrása:

```json
"repositories": {
    "commerce-stripe": { "type": "vcs", "url": "https://github.com/weboldalnet/commerce-stripe" }
}
```

## Konfiguráció

Két forrás, ebben a sorrendben: **az admin beállításoldal** (`/webshop/stripe/settings`, 1.1.0 óta), hiányában a
**`.env`**. A csak `.env`-ből beállított telepítések frissítés után is változatlanul működnek.

### Admin (`/webshop/stripe/settings`)

A commerce-barion beállításoldalának felépítésében: modul állapota (bekapcsolás, megnevezés a pénztárban), környezet
(a titkos kulcs előtagjából), hitelesítés (titkos kulcs, webhook aláíró titok, publikus kulcs), fizetési beállítások
(fizetési lehetőségek, fizetési ablak), megjelenés (fizetőoldal nyelve), webhook- és visszatérési cím, kapcsolatteszt.

- A titkos kulcs és a webhook-titok **titkosítva** kerül a `commerce_stripe_settings` táblába (APP_KEY), az oldalra
  soha nem kerül vissza (csak előtag + utolsó 4 karakter). Üresen hagyva megmarad; a „törlés” jelölővel az adminban
  mentett érték törölhető, ekkor újra a `.env` érvényes.
- Mentéskor ellenőrzi a kulcsok formáját, és hogy a publikus és a titkos kulcs ugyanabból a környezetből való-e.
- Ajánlott a **korlátozott kulcs** (`rk_…`) egyetlen joggal: *Checkout Sessions — Write*. A kapcsolatteszt is ezzel a
  joggal fut (munkamenet-lista).
- Frissítés után: `php artisan migrate` (a tábla; amíg nincs, a `.env` érvényes).

### .env

```env
COMMERCE_STRIPE_SECRET_KEY=sk_test_...        # sk_live_… = éles terhelés!
COMMERCE_STRIPE_WEBHOOK_SECRET=whsec_...      # Dashboard › Developers › Webhooks › a végpont „Signing secret”-je
# nem kötelezők:
COMMERCE_STRIPE_ENABLED=true
COMMERCE_STRIPE_LABEL="Bankkártyás fizetés (Stripe)"
COMMERCE_STRIPE_LOCALE=hu
COMMERCE_STRIPE_PAYMENT_METHOD_TYPES=          # üresen a Dashboardon bekapcsolt módok (kártya, Apple / Google Pay …)
COMMERCE_STRIPE_SESSION_LIFETIME=3600          # 1800–86400 mp
```

Titkos kulcs nélkül (sem az adminban, sem a `.env`-ben) a provider nem regisztrálódik (nem kínálható fizetési módként).

### Nyelv

A fizetőoldal nyelve a beállított `locale` (admin, hiányában `COMMERCE_STRIPE_LOCALE`). Ha a hívó nem magyar nyelvű
rendelést ad át (`PaymentRequestData::$language`, pl. a többnyelvű webshop `EN`-t), a fizetőoldal a rendelés nyelvén
nyílik meg, ha a Stripe ismeri. A csomag saját szövegei (a fizetés leírása: „Rendelés …”, név nélküli tétel) szintén a
rendelés nyelvén: `resources/lang/{hu,en}/checkout.php`; a projekt `lang/vendor/commerce-stripe/{nyelv}/checkout.php`
fájlja kulcsonként felülírja őket (`php artisan vendor:publish --tag=commerce-stripe-lang`).

A provider a kulccsal magától regisztrálódik a commerce-core `PaymentManager`-ébe (a `COMMERCE_PAYMENT_ENABLED` listába
nem kell felvenni); ha ez legyen az alapértelmezett fizetési mód: `COMMERCE_PAYMENT_DEFAULT=stripe`.

## Folyamat

1. A gazda-alkalmazás a `CommerceOrderProcessor::process()`-szel (vagy `PaymentManager::createPayment('stripe', …)`)
   indítja a fizetést: `amount` (bruttó), `currency`, `order_id`, `order_number`, `customer_email`, `items`
   (`name`, `quantity`, `unit_price` bruttó), `return_url` (az alkalmazás eredményoldala), és nem kötelezően
   `extra.cancel_url` (ha a vevő a Stripe oldalán visszalép). A válasz `redirectUrl`-je a Stripe Checkout.
2. Sikeres fizetés után a Stripe a `/commerce/stripe/return?session_id=…` végpontra küldi a vevőt: a csomag a Stripe
   API-tól lekérdezi a munkamenetet, feldolgozza (`PaymentSucceeded` esemény), majd a `return_url`-re irányít (csak az
   alkalmazás saját hostjára).
3. A végleges állapotot a **webhook** is rögzíti: `POST /commerce/stripe/webhook` (a `web` csoporton kívül, CSRF nélkül,
   Stripe-aláírással). Események: `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `checkout.session.async_payment_failed`, `checkout.session.expired`.
4. Elveszett webhookra: a commerce-core (az ütemezett egyeztetést tartalmazó verziótól) 15 percenként magától lekérdezi
   a függő fizetéseket (`commerce:reconcile-payments`, a szerver `schedule:run` cronjával); kézzel, vagy régebbi core-nál:
   `php artisan commerce-stripe:reconcile`.

A gazda-alkalmazás a commerce-core eseményeire (`PaymentSucceeded`, `PaymentFailed`, `PaymentCancelled`) iratkozik fel,
és ott teljesíti a rendelést (zárolt sorral, az összeg és a pénznem ellenőrzésével).

## Pénznem

A Stripe a HUF-ot kéttizedes pénznemként kezeli: az összeg fillérben megy (`StripeClientFactory::toMinor`). Egész
forintos árakkal dolgozz (a Stripe a HUF-terhelésnél egész forintot vár).

## Helyi tesztelés

- Tesztkártya: `4242 4242 4242 4242`, bármilyen jövőbeli lejárat és CVC.
- A webhook localhostra nem érkezik meg: a visszatérő végpont és a `commerce-stripe:reconcile` ettől még véglegesít,
  vagy a Stripe CLI: `stripe listen --forward-to http://localhost:<port>/commerce/stripe/webhook`.
