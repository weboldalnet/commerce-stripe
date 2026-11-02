## [1.0.0] - 2026-11-01
### Első változat
- Stripe Checkout provider a commerce-core-hoz (createPayment, handleReturn, handleCallback, queryStatus, refund).
- Visszatérő végpont (`/commerce/stripe/return`) a Stripe API-s lekérdezéssel, webhook (`/commerce/stripe/webhook`,
  aláírás-ellenőrzéssel, a `web` csoporton kívül), egyeztető parancs (`commerce-stripe:reconcile`).
- Admin beállításoldal (`/webshop/stripe/settings`): mód, maszkolt kulcsok, webhook-cím, kapcsolatteszt.
- Első felhasználás: K&K Tudatos Könyvelés, KIVA e-book.
- Követelmény: `weboldalnet/commerce-core` ^1.0.8.
