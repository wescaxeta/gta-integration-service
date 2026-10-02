<?php declare(strict_types=1);

namespace Gta\Tests\Unit\Domain;

use DateTimeImmutable;
use Gta\Domain\ChaveIdempotencia;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\DadoInvalido;
use Gta\Domain\Exception\TransicaoInvalida;
use Gta\Domain\Finalidade;
use Gta\Domain\Gta;
use Gta\Domain\StatusGta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(Gta::class)]
#[CoversClass(CodigoPropriedade::class)]
#[CoversClass(ChaveIdempotencia::class)]
#[CoversClass(DadoInvalido::class)]
#[CoversClass(TransicaoInvalida::class)]
final class GtaTest extends TestCase
{
    private DateTimeImmutable $agora;

    protected function setUp(): void
    {
        $this->agora = new DateTimeImmutable('2026-03-10 09:00:00+00:00');
    }

    #[Test]
    public function emiteComValidadeDeCincoDias(): void
    {
        $gta = $this->emitir();

        self::assertSame(StatusGta::Emitida, $gta->status);
        self::assertSame('2026-03-15 09:00', $gta->validaAte->format('Y-m-d H:i'));
        self::assertNull($gta->canceladaEm);
    }

    #[Test]
    public function origemIgualAoDestinoLancaExcecao(): void
    {
        $this->expectException(DadoInvalido::class);
        $this->expectExceptionMessage('devem ser diferentes');

        $this->emitir(destino: 'GO000001');
    }

    #[Test]
    public function quantidadeZeroLancaExcecao(): void
    {
        $this->expectException(DadoInvalido::class);

        $this->emitir(quantidade: 0);
    }

    #[Test]
    public function cancelaDentroDaValidade(): void
    {
        $gta     = $this->emitir();
        $momento = $this->agora->modify('+1 day');

        $gta->cancelar($momento);

        self::assertSame(StatusGta::Cancelada, $gta->status);
        self::assertEquals($momento, $gta->canceladaEm);
    }

    #[Test]
    public function naoCancelaDuasVezes(): void
    {
        $gta = $this->emitir();
        $gta->cancelar($this->agora);

        $this->expectException(TransicaoInvalida::class);
        $this->expectExceptionMessage('já está cancelada');

        $gta->cancelar($this->agora);
    }

    #[Test]
    public function naoCancelaGtaVencida(): void
    {
        $gta = $this->emitir();

        $this->expectException(TransicaoInvalida::class);
        $this->expectExceptionMessage('vencida');

        $gta->cancelar($this->agora->modify('+6 days'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function codigosInvalidos(): iterable
    {
        yield 'sem UF' => ['000001'];
        yield 'poucos dígitos' => ['GO123'];
        yield 'letras no número' => ['GO00000A'];
        yield 'vazio' => [''];
    }

    #[Test]
    #[DataProvider('codigosInvalidos')]
    public function rejeitaCodigoDePropriedadeInvalido(string $codigo): void
    {
        $this->expectException(DadoInvalido::class);

        new CodigoPropriedade($codigo);
    }

    #[Test]
    public function normalizaCodigoDePropriedade(): void
    {
        $codigo = new CodigoPropriedade(' go000123 ');

        self::assertSame('GO000123', $codigo->valor);
        self::assertSame('GO', $codigo->uf());
    }

    #[Test]
    public function chaveDeIdempotenciaIgnoraOrdemDosCampos(): void
    {
        $a = ChaveIdempotencia::para('pedido-0001', ['origem' => 'GO000001', 'quantidade' => 5]);
        $b = ChaveIdempotencia::para('pedido-0001', ['quantidade' => 5, 'origem' => 'GO000001']);
        $c = ChaveIdempotencia::para('pedido-0001', ['quantidade' => 6, 'origem' => 'GO000001']);

        self::assertTrue($a->mesmaRequisicao($b));
        self::assertFalse($a->mesmaRequisicao($c));
    }

    #[Test]
    public function rejeitaChaveDeIdempotenciaCurta(): void
    {
        $this->expectException(DadoInvalido::class);

        ChaveIdempotencia::para('abc', []);
    }

    private function emitir(string $destino = 'GO000002', int $quantidade = 10): Gta
    {
        return Gta::emitir(
            id: Uuid::uuid7(),
            origem: new CodigoPropriedade('GO000001'),
            destino: new CodigoPropriedade($destino),
            especie: Especie::Bovino,
            quantidade: $quantidade,
            finalidade: Finalidade::Abate,
            agora: $this->agora,
            chaveIdempotencia: ChaveIdempotencia::para('pedido-0001', ['q' => $quantidade]),
        );
    }
}
