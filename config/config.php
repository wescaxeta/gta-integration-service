<?php declare(strict_types=1);

use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;

$aggregator = new ConfigAggregator([
    Laminas\Diactoros\ConfigProvider::class,
    Laminas\HttpHandlerRunner\ConfigProvider::class,
    Laminas\InputFilter\ConfigProvider::class,
    Laminas\Filter\ConfigProvider::class,
    Laminas\Validator\ConfigProvider::class,
    Mezzio\ConfigProvider::class,
    Mezzio\Helper\ConfigProvider::class,
    Mezzio\Router\ConfigProvider::class,
    Mezzio\Router\FastRouteRouter\ConfigProvider::class,
    Mezzio\ProblemDetails\ConfigProvider::class,
    Gta\ConfigProvider::class,

    // Configuração por ambiente: *.global.php versionados, *.local.php ignorados pelo Git.
    new PhpFileProvider(__DIR__ . '/autoload/{,*.}{global,local}.php'),
]);

return $aggregator->getMergedConfig();
