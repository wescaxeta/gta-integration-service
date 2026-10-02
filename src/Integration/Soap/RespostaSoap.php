<?php declare(strict_types=1);

namespace Gta\Integration\Soap;

use Gta\Domain\Exception\IntegracaoIndisponivel;

/**
 * Lê campos da resposta SOAP (stdClass) validando o tipo de cada um.
 * Um sistema externo pode mudar o contrato sem avisar: falhamos cedo e com mensagem clara.
 */
final class RespostaSoap
{
    private const string SISTEMA = 'Cadastro agropecuário (SOAP)';

    public static function objeto(object $resposta, string $campo): object
    {
        $valor = self::campo($resposta, $campo);

        return is_object($valor) ? $valor : throw self::tipoInvalido($campo, 'objeto');
    }

    public static function string(object $resposta, string $campo): string
    {
        $valor = self::campo($resposta, $campo);

        return is_string($valor) ? $valor : throw self::tipoInvalido($campo, 'string');
    }

    public static function int(object $resposta, string $campo): int
    {
        $valor = self::campo($resposta, $campo);

        return is_int($valor) ? $valor : throw self::tipoInvalido($campo, 'int');
    }

    public static function bool(object $resposta, string $campo): bool
    {
        $valor = self::campo($resposta, $campo);

        return is_bool($valor) ? $valor : throw self::tipoInvalido($campo, 'boolean');
    }

    private static function campo(object $resposta, string $campo): mixed
    {
        $campos = get_object_vars($resposta);

        if (!array_key_exists($campo, $campos)) {
            throw IntegracaoIndisponivel::respostaInvalida(self::SISTEMA, sprintf('campo "%s" ausente', $campo));
        }

        return $campos[$campo];
    }

    private static function tipoInvalido(string $campo, string $esperado): IntegracaoIndisponivel
    {
        return IntegracaoIndisponivel::respostaInvalida(
            self::SISTEMA,
            sprintf('campo "%s" deveria ser %s', $campo, $esperado),
        );
    }
}
