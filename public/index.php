<?php declare(strict_types=1);

use Mezzio\Application;
use Psr\Container\ContainerInterface;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

(static function (): void {
    /** @var ContainerInterface $container */
    $container = require 'config/container.php';

    /** @var Application $app */
    $app = $container->get(Application::class);

    (require 'config/pipeline.php')($app);
    (require 'config/routes.php')($app);

    $app->run();
})();
