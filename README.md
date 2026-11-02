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

## Konfiguráció (.env)

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

A kulcsok CSAK a `.env`-ben élnek: az admin beállításoldal (`/webshop/stripe/settings`) maszkolva mutatja őket, és
kapcsolattesztet ad, de írni nem enged. Titkos kulcs nélkül a provider nem regisztrálódik (nem kínálható fizetési módként).

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
4. Elveszett webhookra: `php artisan commerce-stripe:reconcile` (a függő tranzakciókat kérdezi le; ütemezhető).

A gazda-alkalmazás a commerce-core eseményeire (`PaymentSucceeded`, `PaymentFailed`, `PaymentCancelled`) iratkozik fel,
és ott teljesíti a rendelést (zárolt sorral, az összeg és a pénznem ellenőrzésével).

## Pénznem

A Stripe a HUF-ot kéttizedes pénznemként kezeli: az összeg fillérben megy (`StripeClientFactory::toMinor`). Egész
forintos árakkal dolgozz (a Stripe a HUF-terhelésnél egész forintot vár).

## Helyi tesztelés

- Tesztkártya: `4242 4242 4242 4242`, bármilyen jövőbeli lejárat és CVC.
- A webhook localhostra nem érkezik meg: a visszatérő végpont és a `commerce-stripe:reconcile` ettől még véglegesít,
  vagy a Stripe CLI: `stripe listen --forward-to http://localhost:<port>/commerce/stripe/webhook`.
