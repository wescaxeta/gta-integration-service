<?php declare(strict_types=1);

namespace Gta\Infrastructure\Factory;

use Gta\Infrastructure\Configuracao;
use PDO;
use Psr\Container\ContainerInterface;

final class PdoFactory
{
    public function __invoke(ContainerInterface $container): PDO
    {
        $config = Configuracao::do($container);

        return new PDO(
            $config->string('db', 'dsn'),
            $config->string('db', 'usuario'),
            $config->string('db', 'senha'),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        );
    }
}
