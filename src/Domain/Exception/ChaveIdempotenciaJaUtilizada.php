<?php declare(strict_types=1);

namespace Gta\Domain\Exception;

/**
 * Lançada pelo repositório quando duas requisições concorrentes tentam gravar a mesma
 * chave de idempotência. O caso de uso trata e devolve a GTA gravada pela outra requisição.
 */
final class ChaveIdempotenciaJaUtilizada extends GtaException
{
    public static function chave(string $chave): self
    {
        return new self(sprintf('Chave de idempotência "%s" já gravada.', $chave));
    }
}
