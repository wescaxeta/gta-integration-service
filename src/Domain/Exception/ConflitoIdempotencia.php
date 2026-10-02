<?php declare(strict_types=1);

namespace Gta\Domain\Exception;

/**
 * A chave de idempotência já foi usada com um conteúdo de requisição diferente.
 */
final class ConflitoIdempotencia extends GtaException
{
    public static function conteudoDiferente(string $chave): self
    {
        return new self(sprintf(
            'Idempotency-Key "%s" já foi usada com outro conteúdo. Gere uma nova chave para uma nova emissão.',
            $chave,
        ));
    }
}
