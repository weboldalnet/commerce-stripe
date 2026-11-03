<?php

// A Stripe fizetőoldalon megjelenő saját szövegek, a rendelés nyelvén. A projekt
// lang/vendor/commerce-stripe/hu/checkout.php fájlja kulcsonként felülírja őket.
return [
    // a fizetés leírása, és összevont tételnév, ha a tételek összege nem adja ki a végösszeget
    'order' => 'Rendelés :number',
    // név nélküli tétel
    'item' => 'Tétel',
];
