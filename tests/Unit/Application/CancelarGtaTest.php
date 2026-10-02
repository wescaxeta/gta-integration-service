<?php declare(strict_types=1);

namespace Gta\Tests\Unit\Application;

use Gta\Application\CancelarGta;
use Gta\Domain\ChaveIdempotencia;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\GtaNaoEncontrada;
use Gta\Domain\Finalidade;
use Gta\Domain\Gta;
use Gta\Domain\StatusGta;
use Gta\Tests\Double\GtaRepositoryEmMemoria;
use Gta\Tests\Double\RelogioCongelado;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

#[CoversClass(CancelarGta::class)]
#[CoversClass(GtaNaoEncontrada::class)]
final class CancelarGtaTest extends TestCase
{
    #[Test]
    public function cancelaEPersisteANovaSituacao(): void
    {
        $relogio     = new RelogioCongelado();
        $repositorio = new GtaRepositoryEmMemoria();
        $gta         = Gta::emitir(
            Uuid::uuid7(),
            new CodigoPropriedade('GO000001'),
            new CodigoPropriedade('GO000002'),
            Especie::Bovino,
            10,
            Finalidade::Abate,
            $relogio->now(),
            ChaveIdempotencia::para('pedido-0001', []),
        );
        $repositorio->adicionar($gta);
        $relogio->avancar('+2 hours');

        new CancelarGta($repositorio, $relogio, new NullLogger())->executar($gta->id);

        $salva = $repositorio->buscar($gta->id);
        self::assertNotNull($salva);
        self::assertSame(StatusGta::Cancelada, $salva->status);
        self::assertEquals($relogio->now(), $salva->canceladaEm);
    }

    #[Test]
    public function gtaInexistenteLancaExcecao(): void
    {
        $this->expectException(GtaNaoEncontrada::class);

        new CancelarGta(new GtaRepositoryEmMemoria(), new RelogioCongelado(), new NullLogger())->executar(Uuid::uuid7());
    }
}
