<?php declare(strict_types=1);

namespace Gta\Infrastructure\Factory;

use Gta\Infrastructure\Configuracao;
use Gta\Integration\Retry\RetryPolicy;
use Psr\Container\ContainerInterface;

final class RetryPolicyFactory
{
    public function __invoke(ContainerInterface $container): RetryPolicy
    {
        $config = Configuracao::do($container);

        return new RetryPolicy(
            maxTentativas: $config->int('retry', 'max_tentativas'),
            esperaInicialMs: $config->int('retry', 'espera_inicial_ms'),
        );
    }
}
