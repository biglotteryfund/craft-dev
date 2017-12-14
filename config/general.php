<?php
/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 */

return [
    // Global settings
    '*' => [
        // Default Week Start Day (0 = Sunday, 1 = Monday...)
        'defaultWeekStartDay' => 0,

        // Enable CSRF Protection (recommended, will be enabled by default in Craft 3)
        'enableCsrfProtection' => true,

        // Whether "index.php" should be visible in URLs
        'omitScriptNameInUrls' => true,

        // Control Panel trigger word
        'cpTrigger' => 'admin',

        'securityKey' => 'DugP0KMpm7-KvS9ID8vDmL3dPXs8S0uN',

        'allowAutoUpdates' => false
    ],

    // Dev environment settings
    'dev' => [
        // Base site URL
        'siteUrl' => null,

        // Dev Mode (see https://craftcms.com/support/dev-mode)
        'devMode' => true,
        'defaultCookieDomain' => getenv('CUSTOM_COOKIE_DOMAIN')
    ],

    // Staging environment settings
    'test' => [
        // Base site URL
        'siteUrl' => null,
        // avoid breaking HTTPS
        'baseCpUrl' => getenv('BASE_CP_URL')
    ],

    // Production environment settings
    'production' => [
        // Base site URL
        'siteUrl' => null,
        // avoid breaking HTTPS
        'baseCpUrl' => getenv('BASE_CP_URL')
    ],
];
