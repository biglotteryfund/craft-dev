<?php

return [
    'modules' => [
        'control-panel' => biglotteryfund\Module::class,
    ],
    'components' => [
        'session' => function() {
            // Get the default component config
            $config = craft\helpers\App::sessionConfig();

            // Override the class to use DB session class
            $config['class'] = yii\web\DbSession::class;

            // Set the session table name
            $config['sessionTable'] = craft\db\Table::PHPSESSIONS;

            // Instantiate and return it
            return Craft::createObject($config);
        },
        'cache' => craft\cache\DbCache::class,
    ],
    'bootstrap' => [
        'control-panel',
    ],
];