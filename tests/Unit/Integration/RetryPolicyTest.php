<?php declare(strict_types=1);

namespace Gta\Tests\Unit\Integration;

use Gta\Integration\Retry\RetryPolicy;
use Gta\Integration\Retry\TentativasEsgotadas;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(RetryPolicy::class)]
#[CoversClass(TentativasEsgotadas::class)]
final class RetryPolicyTest extends TestCase
{
    /** @var list<int> */
    private array $esperas = [];

    #[Test]
    public function devolveOResultadoQuandoUmaNovaTentativaFunciona(): void
    {
        $resultado = $this->politica()->executar(
            static fn(int $tentativa): string => $tentativa < 3
                ? throw new RuntimeException('timeout')
                : sprintf('ok na tentativa %d', $tentativa),
            static fn(Throwable $erro): bool => true,
        );

        self::assertSame('ok na tentativa 3', $resultado);
        self::assertSame([100, 200], $this->esperas, 'backoff exponencial entre as tentativas');
    }

    #[Test]
    public function desisteAposOLimiteDeTentativas(): void
    {
        $this->expectException(TentativasEsgotadas::class);
        $this->expectExceptionMessage('Operação falhou após 3 tentativa(s): fora do ar');

        try {
            $this->politica()->executar(
                static fn(int $tentativa): never => throw new RuntimeException('fora do ar'),
                static fn(Throwable $erro): bool => true,
            );
        } finally {
            self::assertSame([100, 200], $this->esperas);
        }
    }

    #[Test]
    public function naoRepeteFalhaPermanente(): void
    {
        $tentativas = 0;

        $this->expectException(LogicException::class);

        try {
            $this->politica()->executar(
                static function () use (&$tentativas): never {
                    $tentativas++;

                    throw new LogicException('requisição inválida');
                },
                static fn(Throwable $erro): bool => !$erro instanceof LogicException,
            );
        } finally {
            self::assertSame(1, $tentativas);
            self::assertSame([], $this->esperas);
        }
    }

    #[Test]
    public function rejeitaConfiguracaoInvalida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryPolicy(maxTentativas: 0);
    }

    private function politica(): RetryPolicy
    {
        return new RetryPolicy(
            maxTentativas: 3,
            esperaInicialMs: 100,
            esperar: function (int $ms): void {
                $this->esperas[] = $ms;
            },
        );
    }
}
