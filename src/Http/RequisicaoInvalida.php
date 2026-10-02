<?php declare(strict_types=1);

namespace Gta\Http;

use RuntimeException;

/**
 * Erro de formato da requisição HTTP (antes de chegar ao domínio).
 */
final class RequisicaoInvalida extends RuntimeException
{
    /**
     * @param array<string, list<string>> $erros mensagens por campo
     */
    private function __construct(
        string $mensagem,
        public readonly int $status,
        public readonly array $erros = [],
    ) {
        parent::__construct($mensagem);
    }

    public static function semChaveIdempotencia(): self
    {
        return new self('O header Idempotency-Key é obrigatório para emitir uma GTA.', 400);
    }

    public static function corpoNaoJson(): self
    {
        return new self('O corpo da requisição deve ser um objeto JSON.', 400);
    }

    /**
     * @param array<string, list<string>> $erros
     */
    public static function camposInvalidos(array $erros): self
    {
        return new self('Um ou mais campos são inválidos.', 422, $erros);
    }
}
