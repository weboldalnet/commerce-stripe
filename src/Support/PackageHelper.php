<?php

namespace Weboldalnet\CommerceStripe\Support;

class PackageHelper
{
    const PACKAGE_NAME = 'Stripe fizetési modul';
    const PACKAGE_PREFIX = 'commerce-stripe';

    const PACKAGE_LIST = [
        'routes' => [
            'name' => 'routes | routes/web.php',
            'source' => __DIR__.'/../../routes/web.php',
            'destination' => '/routes/commerce-stripe.php',
        ],
        'settings' => [
            'name' => 'settings | settings/',
            'source' => __DIR__.'/../../settings',
            'destination' => '/settings/commerce-stripe',
        ],
        'config' => [
            'name' => 'config | config/commerce-stripe.php',
            'source' => __DIR__.'/../../config/commerce-stripe.php',
            'destination' => '/config/commerce-stripe.php',
        ],
    ];

    // a fizetőoldal saját szövegeinek nyelvi fájljai (publish: commerce-stripe-lang)
    const LANG_SOURCE = __DIR__.'/../../resources/lang';

    const PACKAGE_VIEW_EXTENDS = [
        'sidebar' => [
            'view_path' => '/resources/views/admin/package-container/admin-p-sidebar.blade.php',
            'include' => "@include('" . self::PACKAGE_PREFIX . "::admin.sidebar')"
        ],
        'package-settings' => [
            'view_path' => '/resources/views/admin/package-settings/package-settings-container.blade.php',
            'include' => "@include('" . self::PACKAGE_PREFIX . "::admin.package-functions')"
        ],
    ];
}
