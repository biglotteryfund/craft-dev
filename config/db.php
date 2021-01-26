<?php
///**
// * Database Configuration
// *
// * All of your system's database connection settings go in here. You can see a
// * list of the available settings in vendor/craftcms/cms/src/config/DbConfig.php.
// */

use Biglotteryfund\conf\ConfigManager;
$config = new Biglotteryfund\conf\ConfigManager;

return [
    'driver' => 'mysql',
    'server' => $config->getConfig('DB_SERVER', $_ENV['CRAFT_DB_SERVER']),
    'user' => $config->getConfig('DB_USER', $_ENV['CRAFT_DB_USER']),
    'password' => $config->getConfig('DB_PASSWORD', $_ENV['CRAFT_DB_PASSWORD']),
    'database' => $config->getConfig('DB_DATABASE', $_ENV['CRAFT_DB_DATABASE']),
    'schema' => $_ENV['CRAFT_DB_SCHEMA'] ?: 'public',
    'tablePrefix' => $_ENV['CRAFT_DB_TABLE_PREFIX'] ?: '',
    'port' => $_ENV['CRAFT_DB_PORT'] ?: 3306
];
