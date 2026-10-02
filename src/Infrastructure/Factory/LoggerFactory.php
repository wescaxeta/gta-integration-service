<?php declare(strict_types=1);

namespace Gta\Infrastructure\Factory;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Logs estruturados em JSON no stderr, prontos para coleta pelo Docker ou por um agregador.
 */
final class LoggerFactory
{
    public function __invoke(ContainerInterface $container): LoggerInterface
    {
        $handler = new StreamHandler('php://stderr');
        $handler->setFormatter(new JsonFormatter());

        return new Logger('gta', [$handler], [new PsrLogMessageProcessor()]);
    }
}
