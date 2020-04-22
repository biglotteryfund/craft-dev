<?php

namespace biglotteryfund\conf;

use Noodlehaus\Config;
use Noodlehaus\Exception\FileNotFoundException;


class ConfigManager
{
    private $conf;

    public function __construct()
    {
        try {
            $this->conf = new Config('/etc/craft/parameters.json');
        } catch (FileNotFoundException $e) {
            // no secret file found so we should be in dev mode
            if (CRAFT_ENVIRONMENT !== 'dev') {
                debug_print_backtrace();
                trigger_error("A secrets file is required on non-development environments", E_USER_ERROR);
                exit();
            }
        }
    }

    public function getConfig($key, $fallback)
    {
        if ($this->conf) {
            return $this->conf->get($key, $fallback);
        } else {
            return $fallback;
        }
    }

}
