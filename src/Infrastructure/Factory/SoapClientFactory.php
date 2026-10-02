<?php declare(strict_types=1);

namespace Gta\Infrastructure\Factory;

use Gta\Infrastructure\Configuracao;
use Psr\Container\ContainerInterface;
use SoapClient;

final class SoapClientFactory
{
    public function __invoke(ContainerInterface $container): SoapClient
    {
        $config  = Configuracao::do($container);
        $timeout = $config->int('soap', 'timeout_segundos');

        return new SoapClient($config->string('soap', 'wsdl'), [
            // O WSDL é versionado no repositório; o endpoint muda por ambiente.
            'location'   => $config->string('soap', 'endpoint'),
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_DISK,
            // connection_timeout cobre só o connect; o timeout de leitura vem do stream context.
            'connection_timeout' => $timeout,
            'stream_context'     => stream_context_create(['http' => ['timeout' => $timeout]]),
            'features'           => SOAP_SINGLE_ELEMENT_ARRAYS,
        ]);
    }
}
