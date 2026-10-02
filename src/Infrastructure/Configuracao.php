<?php declare(strict_types=1);

namespace Gta\Infrastructure;

use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * Acesso tipado à configuração da aplicação (chave "gta" do config agregado),
 * para que as factories não manipulem arrays sem tipo.
 */
final readonly class Configuracao
{
    /**
     * @param array<mixed> $valores
     */
    private function __construct(
        private array $valores,
    ) {}

    public static function do(ContainerInterface $container): self
    {
        $config = $container->get('config');

        if (!is_array($config) || !is_array($config['gta'] ?? null)) {
            throw new UnexpectedValueException('Configuração "gta" ausente.');
        }

        return new self($config['gta']);
    }

    public function string(string ...$caminho): string
    {
        $caminho = array_values($caminho);
        $valor   = $this->valor($caminho);

        return is_string($valor) ? $valor : throw $this->tipoInvalido($caminho, 'string');
    }

    public function int(string ...$caminho): int
    {
        $caminho = array_values($caminho);
        $valor   = $this->valor($caminho);

        return is_int($valor) ? $valor : throw $this->tipoInvalido($caminho, 'int');
    }

    /**
     * @param list<string> $caminho
     */
    private function valor(array $caminho): mixed
    {
        $atual = $this->valores;

        foreach ($caminho as $chave) {
            if (!is_array($atual) || !array_key_exists($chave, $atual)) {
                throw new UnexpectedValueException(sprintf('Configuração "gta.%s" ausente.', implode('.', $caminho)));
            }

            $atual = $atual[$chave];
        }

        return $atual;
    }

    /**
     * @param list<string> $caminho
     */
    private function tipoInvalido(array $caminho, string $tipo): UnexpectedValueException
    {
        return new UnexpectedValueException(sprintf('Configuração "gta.%s" deve ser %s.', implode('.', $caminho), $tipo));
    }
}
