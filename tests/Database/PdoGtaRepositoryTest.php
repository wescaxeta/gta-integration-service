<?php declare(strict_types=1);

namespace Gta\Tests\Database;

use DateTimeImmutable;
use Gta\Domain\ChaveIdempotencia;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\ChaveIdempotenciaJaUtilizada;
use Gta\Domain\Finalidade;
use Gta\Domain\Gta;
use Gta\Domain\StatusGta;
use Gta\Infrastructure\Persistence\PdoGtaRepository;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Roda contra um PostgreSQL de verdade. Configure TEST_DB_DSN (ex.: no CI ou via
 * `make test`); sem ele, os testes são ignorados.
 */
#[CoversClass(PdoGtaRepository::class)]
#[Group('database')]
final class PdoGtaRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoGtaRepository $repositorio;

    protected function setUp(): void
    {
        $dsn = getenv('TEST_DB_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Defina TEST_DB_DSN para rodar os testes de banco.');
        }

        $this->pdo = new PDO($dsn, (string) getenv('TEST_DB_USER'), (string) getenv('TEST_DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        self::assertIsString($schema);

        $this->pdo->exec('DROP TABLE IF EXISTS gta');
        $this->pdo->exec($schema);

        $this->repositorio = new PdoGtaRepository($this->pdo);
    }

    #[Test]
    public function gravaERecuperaTodosOsCampos(): void
    {
        $gta = $this->novaGta('pedido-0001');
        $this->repositorio->adicionar($gta);

        $lida = $this->repositorio->buscar($gta->id);

        self::assertNotNull($lida);
        self::assertTrue($gta->id->equals($lida->id));
        self::assertSame('GO000001', $lida->origem->valor);
        self::assertSame(Especie::Bovino, $lida->especie);
        self::assertSame(25, $lida->quantidade);
        self::assertSame(StatusGta::Emitida, $lida->status);
        self::assertEquals($gta->emitidaEm, $lida->emitidaEm);
        self::assertEquals($gta->validaAte, $lida->validaAte);
        self::assertTrue($gta->chaveIdempotencia->mesmaRequisicao($lida->chaveIdempotencia));
    }

    #[Test]
    public function buscaPorChaveDeIdempotencia(): void
    {
        $gta = $this->novaGta('pedido-0002');
        $this->repositorio->adicionar($gta);

        $lida = $this->repositorio->buscarPorChaveIdempotencia('pedido-0002');

        self::assertNotNull($lida);
        self::assertTrue($gta->id->equals($lida->id));
        self::assertNull($this->repositorio->buscarPorChaveIdempotencia('nao-existe'));
    }

    #[Test]
    public function chaveDuplicadaViraExcecaoDeDominio(): void
    {
        $this->repositorio->adicionar($this->novaGta('pedido-0003'));

        $this->expectException(ChaveIdempotenciaJaUtilizada::class);

        $this->repositorio->adicionar($this->novaGta('pedido-0003'));
    }

    #[Test]
    public function persisteOCancelamento(): void
    {
        $gta = $this->novaGta('pedido-0004');
        $this->repositorio->adicionar($gta);

        $gta->cancelar($gta->emitidaEm->modify('+1 hour'));
        $this->repositorio->atualizar($gta);

        $lida = $this->repositorio->buscar($gta->id);
        self::assertNotNull($lida);
        self::assertSame(StatusGta::Cancelada, $lida->status);
        self::assertEquals($gta->canceladaEm, $lida->canceladaEm);
    }

    private function novaGta(string $chave): Gta
    {
        return Gta::emitir(
            Uuid::uuid7(),
            new CodigoPropriedade('GO000001'),
            new CodigoPropriedade('GO000002'),
            Especie::Bovino,
            25,
            Finalidade::Engorda,
            new DateTimeImmutable('2026-03-10 09:00:00.123456+00:00'),
            ChaveIdempotencia::para($chave, ['quantidade' => 25]),
        );
    }
}
