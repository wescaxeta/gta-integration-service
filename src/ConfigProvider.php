<?php declare(strict_types=1);

namespace Gta;

use Gta\Application\CancelarGta;
use Gta\Application\EmitirGta;
use Gta\Domain\CadastroAgropecuario;
use Gta\Domain\GtaRepository;
use Gta\Http\Handler\CancelarGtaHandler;
use Gta\Http\Handler\ConsultarGtaHandler;
use Gta\Http\Handler\EmitirGtaHandler;
use Gta\Http\Handler\HealthHandler;
use Gta\Http\Middleware\ErrosDeDominioMiddleware;
use Gta\Infrastructure\Clock\RelogioDoSistema;
use Gta\Infrastructure\Factory\LoggerFactory;
use Gta\Infrastructure\Factory\PdoFactory;
use Gta\Infrastructure\Factory\RetryPolicyFactory;
use Gta\Infrastructure\Factory\SoapClientFactory;
use Gta\Infrastructure\Persistence\PdoGtaRepository;
use Gta\Integration\Retry\RetryPolicy;
use Gta\Integration\Soap\SoapCadastroAgropecuario;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use PDO;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use SoapClient;

/**
 * Registro de dependências do módulo. Classes com construtor totalmente tipado
 * são montadas por reflexão; interfaces apontam para a implementação via alias.
 */
final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->dependencias(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function dependencias(): array
    {
        return [
            'aliases' => [
                CadastroAgropecuario::class => SoapCadastroAgropecuario::class,
                GtaRepository::class        => PdoGtaRepository::class,
                ClockInterface::class       => RelogioDoSistema::class,
            ],
            'invokables' => [
                RelogioDoSistema::class => RelogioDoSistema::class,
                HealthHandler::class    => HealthHandler::class,
            ],
            'factories' => [
                PDO::class             => PdoFactory::class,
                SoapClient::class      => SoapClientFactory::class,
                RetryPolicy::class     => RetryPolicyFactory::class,
                LoggerInterface::class => LoggerFactory::class,

                SoapCadastroAgropecuario::class => ReflectionBasedAbstractFactory::class,
                PdoGtaRepository::class         => ReflectionBasedAbstractFactory::class,
                EmitirGta::class                => ReflectionBasedAbstractFactory::class,
                CancelarGta::class              => ReflectionBasedAbstractFactory::class,
                EmitirGtaHandler::class         => ReflectionBasedAbstractFactory::class,
                ConsultarGtaHandler::class      => ReflectionBasedAbstractFactory::class,
                CancelarGtaHandler::class       => ReflectionBasedAbstractFactory::class,
                ErrosDeDominioMiddleware::class => ReflectionBasedAbstractFactory::class,
            ],
        ];
    }
}
