<?php

namespace biglotteryfund\conf;

use Noodlehaus\Config;


class ConfigManager
{
    private $conf;

    public function __construct()
    {
        $this->conf = new Config('/etc/craft/parameters.json');
    }

    public function getConfig($key, $fallback)
    {
        return $this->conf->get($key, $fallback);
    }

}
