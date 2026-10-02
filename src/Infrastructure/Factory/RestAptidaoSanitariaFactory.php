<?php declare(strict_types=1);

namespace Gta\Infrastructure\Factory;

use Gta\Infrastructure\Configuracao;
use Gta\Integration\Rest\RestAptidaoSanitaria;
use Gta\Integration\Retry\RetryPolicy;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

final class RestAptidaoSanitariaFactory
{
    public function __invoke(ContainerInterface $container): RestAptidaoSanitaria
    {
        $config  = Configuracao::do($container);
        $timeout = $config->int('vacinacao', 'timeout_segundos');

        $retry  = $container->get(RetryPolicy::class);
        $logger = $container->get(LoggerInterface::class);

        if (!$retry instanceof RetryPolicy || !$logger instanceof LoggerInterface) {
            throw new UnexpectedValueException('Dependências da integração REST não configuradas.');
        }

        return new RestAptidaoSanitaria(
            new Client([
                'base_uri'        => rtrim($config->string('vacinacao', 'endpoint'), '/') . '/',
                'connect_timeout' => $timeout,
                'timeout'         => $timeout,
                'http_errors'     => true,
            ]),
            $retry,
            $logger,
        );
    }
}
