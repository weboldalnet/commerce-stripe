<?php

// A Stripe fizetőoldalon megjelenő saját szövegek, a rendelés nyelvén. A projekt
// lang/vendor/commerce-stripe/en/checkout.php fájlja kulcsonként felülírja őket.
return [
    // a fizetés leírása, és összevont tételnév, ha a tételek összege nem adja ki a végösszeget
    'order' => 'Order :number',
    // név nélküli tétel
    'item' => 'Item',
];
