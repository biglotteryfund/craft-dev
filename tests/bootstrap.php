<?php
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

$envFile = realpath(__DIR__ . '/../.env');
if (is_string($envFile) && file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable( realpath(__DIR__ . '/../'));
    $dotenv->load();
}
