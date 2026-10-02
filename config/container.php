<?php declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;

/** @var array{dependencies: array<string, mixed>} $config */
$config = require __DIR__ . '/config.php';

$dependencias                       = $config['dependencies'];
$dependencias['services']['config'] = $config;

return new ServiceManager($dependencias);
