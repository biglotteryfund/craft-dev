<?php
///**
// * Database Configuration
// *
// * All of your system's database connection settings go in here. You can see a
// * list of the available settings in vendor/craftcms/cms/src/config/DbConfig.php.
// */

use biglotteryfund\conf\ConfigManager;
$config = new biglotteryfund\conf\ConfigManager;

return [
    'driver' => 'mysql',
    'server' => $config->getConfig('DB_SERVER', getenv('CRAFT_DB_SERVER')),
    'user' => $config->getConfig('DB_USER', getenv('CRAFT_DB_USER')),
    'password' => $config->getConfig('DB_PASSWORD', getenv('CRAFT_DB_PASSWORD')),
    'database' => $config->getConfig('DB_DATABASE', getenv('CRAFT_DB_DATABASE')),
    'schema' => getenv('CRAFT_DB_SCHEMA') ?: 'public',
    'tablePrefix' => getenv('CRAFT_DB_TABLE_PREFIX') ?: '',
    'port' => getenv('CRAFT_DB_PORT') ?: 3306
];
