<?php declare(strict_types=1);

namespace Gta\Domain;

use Gta\Domain\Exception\DadoInvalido;

/**
 * Chave enviada pelo cliente no header Idempotency-Key, junto com a impressão digital
 * (hash) da requisição original. Reenviar a mesma chave com outro conteúdo é erro.
 */
final readonly class ChaveIdempotencia
{
    private const string FORMATO = '/^[A-Za-z0-9_-]{8,64}$/';

    private function __construct(
        public string $chave,
        public string $hashRequisicao,
    ) {}

    /**
     * @param array<string, scalar> $requisicao
     */
    public static function para(string $chave, array $requisicao): self
    {
        if (preg_match(self::FORMATO, $chave) !== 1) {
            throw DadoInvalido::chaveIdempotencia();
        }

        ksort($requisicao);

        return new self($chave, hash('sha256', serialize($requisicao)));
    }

    public static function reconstituir(string $chave, string $hashRequisicao): self
    {
        return new self($chave, $hashRequisicao);
    }

    public function mesmaRequisicao(self $outra): bool
    {
        return $this->chave === $outra->chave && hash_equals($this->hashRequisicao, $outra->hashRequisicao);
    }
}
