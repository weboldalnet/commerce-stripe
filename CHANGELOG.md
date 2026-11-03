## [Unreleased]
### Többnyelvű webshop: a fizetőoldal a vásárló nyelvén (az 1.0.2 után)
- a Checkout `locale`-ja nem magyar rendelésnél a rendelés nyelve (a webshop a `PaymentRequestData::$language`-ben
  adja, pl. `EN` → `en`; a szolgáltatói kódok is: `CZ` → `cs`, `GR` → `el`, `SI` → `sl`); magyar rendelésnél és ha a
  hívó nem ad nyelvet (a core alapértéke `HU`), továbbra is az admin beállítás dönt (pl. `hu` vagy `auto`) — a
  meglévő telepítések működése nem változik
- a fizetés leírása és az összevont tételnév („Rendelés …”), valamint a név nélküli tétel („Tétel”) a rendelés nyelvén:
  `resources/lang/{hu,en}/checkout.php`, kulcs `commerce-stripe::checkout.…`; új publish tag: `commerce-stripe-lang`
- admin: a „Fizetőoldal nyelve” súgója jelzi, hogy a nem magyar rendelések a vásárló nyelvén nyílnak meg

### Admin beállításoldal a commerce-barion mintájára, adatbázisban tárolt beállítások
**1 új migráció** (`commerce_stripe_settings`, `hasTable`-védelemmel) — frissítés után `php artisan migrate`.
Visszafelé kompatibilis: ami az adminban nincs megadva (vagy a tábla még nem létezik), arra a `.env` érvényes, így a
csak `.env`-ből beállított telepítések (pl. K&K Tudatos Könyvelés) változatlanul működnek.

- **Beállításoldal** (`/webshop/stripe/settings`) a Barionéval azonos felépítésben: modul állapota, környezet,
  hitelesítés, fizetési beállítások, megjelenés, webhook és visszatérés, kapcsolatteszt. Nem függ a webshop
  csomagtól (saját értesítés-blokk).
- **Szerkeszthető**: bekapcsolás, megnevezés a pénztárban, titkos kulcs és webhook-titok (titkosítva, maszkolva,
  üresen megmarad, törölhető), publikus kulcs, fizetési lehetőségek (Dashboard szerint / csak kártya), fizetési ablak,
  fizetőoldal nyelve. `StripeSettingsService` (admin → .env), `StripeSetting` modell.
- **Ellenőrzés mentéskor**: kulcsformák (`(sk|rk)_(test|live)_…`, `pk_(test|live)_…`, `whsec_…`), a publikus és a titkos
  kulcs azonos környezete; visszafejthetetlen mentett titok (megváltozott APP_KEY) esetén figyelmeztetés.
- **Kapcsolatteszt** a Checkout-munkamenetek listázásával (korábban egyenleg-lekérés): a csak „Checkout Sessions” jogú
  korlátozott kulccsal (`rk_…`) is működik; a Stripe hibaüzenete nem jelenik meg szó szerint.
- A webhook- és a visszatérési cím a site domainjéből (porttal), másolás gombbal; a helyi `stripe listen` parancs.
- Az elmaradt webhookokat a commerce-core ütemezett `commerce:reconcile-payments` parancsa pótolja (a Stripe a
  `queryStatus()`-szal részt vesz benne); a csomag saját `commerce-stripe:reconcile` parancsa kézi használatra marad.

## [1.0.0] - 2026-11-01
### Első változat
- Stripe Checkout provider a commerce-core-hoz (createPayment, handleReturn, handleCallback, queryStatus, refund).
- Visszatérő végpont (`/commerce/stripe/return`) a Stripe API-s lekérdezéssel, webhook (`/commerce/stripe/webhook`,
  aláírás-ellenőrzéssel, a `web` csoporton kívül), egyeztető parancs (`commerce-stripe:reconcile`).
- Admin beállításoldal (`/webshop/stripe/settings`): mód, maszkolt kulcsok, webhook-cím, kapcsolatteszt.
- Első felhasználás: K&K Tudatos Könyvelés, KIVA e-book.
- Követelmény: `weboldalnet/commerce-core` ^1.0.8.
