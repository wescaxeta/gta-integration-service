<?php declare(strict_types=1);

namespace Gta\Integration\Retry;

use Closure;
use InvalidArgumentException;
use Throwable;

/**
 * Executa uma operação com novas tentativas e backoff exponencial
 * (ex.: 200 ms, 400 ms, 800 ms...). Só repete as falhas que o chamador
 * considerar transitórias.
 */
final readonly class RetryPolicy
{
    /** @var Closure(int): void */
    private Closure $esperar;

    /**
     * @param (Closure(int): void)|null $esperar recebe a espera em milissegundos (injetável para testes)
     */
    public function __construct(
        public int $maxTentativas = 3,
        public int $esperaInicialMs = 200,
        ?Closure $esperar = null,
    ) {
        if ($maxTentativas < 1 || $esperaInicialMs < 0) {
            throw new InvalidArgumentException('Política de retry inválida.');
        }

        $this->esperar = $esperar ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /**
     * @template T
     *
     * @param Closure(int): T          $operacao    recebe o número da tentativa (começa em 1)
     * @param Closure(Throwable): bool $deveRepetir decide se a falha é transitória
     *
     * @return T
     *
     * @throws TentativasEsgotadas quando todas as tentativas falham com erro transitório
     * @throws Throwable           a falha original, sem nova tentativa, quando não é transitória
     */
    public function executar(Closure $operacao, Closure $deveRepetir): mixed
    {
        $tentativa = 1;

        while (true) {
            try {
                return $operacao($tentativa);
            } catch (Throwable $erro) {
                if (!$deveRepetir($erro)) {
                    throw $erro;
                }

                if ($tentativa >= $this->maxTentativas) {
                    throw new TentativasEsgotadas($tentativa, $erro);
                }

                ($this->esperar)($this->esperaInicialMs * 2 ** ($tentativa - 1));
                $tentativa++;
            }
        }
    }
}
