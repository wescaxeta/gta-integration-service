<?php declare(strict_types=1);

namespace Gta\Domain\Exception;

use Throwable;

/**
 * O sistema externo não respondeu (ou respondeu com erro) mesmo após as novas tentativas.
 */
final class IntegracaoIndisponivel extends GtaException
{
    public static function aposTentativas(string $sistema, int $tentativas, ?Throwable $causa = null): self
    {
        return new self(
            sprintf('%s indisponível após %d tentativa(s). Tente novamente em instantes.', $sistema, $tentativas),
            previous: $causa,
        );
    }

    public static function respostaInvalida(string $sistema, string $detalhe): self
    {
        return new self(sprintf('%s retornou uma resposta fora do contrato: %s.', $sistema, $detalhe));
    }
}
