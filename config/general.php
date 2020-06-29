<?php
/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 */

use biglotteryfund\conf\ConfigManager;
$config = new biglotteryfund\conf\ConfigManager;

return [
    // Global settings
    '*' => [
        // Default Week Start Day (0 = Sunday, 1 = Monday...)
        'defaultWeekStartDay' => 1,

        // Use British English (for date formats, mainly)
        'defaultCpLanguage' => 'en-GB',

        // Enable CSRF Protection (recommended, will be enabled by default in Craft 3)
        'enableCsrfProtection' => true,

        // Whether "index.php" should be visible in URLs
        'omitScriptNameInUrls' => true,

        // Control Panel trigger word
        'cpTrigger' => 'admin',

        'securityKey' => $config->getConfig('SECURITY_KEY', $_ENV['SECURITY_KEY']),

        'allowUpdates' => false,
        'useProjectConfigFile' => true,

        // Disable transforms for animated gifs
        'transformGifs' => false,

        // Set file uploads to 20mb maximum
        // This needs to be defined if upload_max_filesize is greater than 16mb (Craft default)
        'maxUploadFileSize' => 20777216,

        // Allow expiring links (eg. shareable previews) to last for a week
        'defaultTokenDuration' => 604800, // one week

    ],

    // Dev environment settings
    'dev' => [
        // Base site URL
        'siteUrl' => $_ENV['SITE_URL'],

        // Dev Mode (see https://craftcms.com/support/dev-mode)
        'devMode' => true,
    ],

    // Staging environment settings
    'test' => [
        // Base site URL
        'siteUrl' => null,
        // avoid breaking HTTPS
        'baseCpUrl' => $config->getConfig('BASE_CP_URL', $_ENV['BASE_CP_URL']),
        'allowAdminChanges' => false,
    ],

    // Production environment settings
    'production' => [
        // Base site URL
        'siteUrl' => null,
        // avoid breaking HTTPS
        'baseCpUrl' => $config->getConfig('BASE_CP_URL', $_ENV['BASE_CP_URL']),
        'allowAdminChanges' => false,
    ],
];
