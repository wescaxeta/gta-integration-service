<?php declare(strict_types=1);

namespace Gta\Tests\Unit\Application;

use Gta\Application\EmitirGta;
use Gta\Application\EmitirGtaComando;
use Gta\Application\ResultadoEmissao;
use Gta\Domain\ChaveIdempotencia;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\ConflitoIdempotencia;
use Gta\Domain\Exception\RegraEmissaoViolada;
use Gta\Domain\Finalidade;
use Gta\Domain\Gta;
use Gta\Domain\Propriedade;
use Gta\Domain\SituacaoPropriedade;
use Gta\Tests\Double\AptidaoSanitariaEmMemoria;
use Gta\Tests\Double\CadastroAgropecuarioEmMemoria;
use Gta\Tests\Double\GtaRepositoryEmMemoria;
use Gta\Tests\Double\RelogioCongelado;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

#[CoversClass(EmitirGta::class)]
#[CoversClass(EmitirGtaComando::class)]
#[CoversClass(ResultadoEmissao::class)]
#[CoversClass(RegraEmissaoViolada::class)]
#[CoversClass(ConflitoIdempotencia::class)]
#[CoversClass(Propriedade::class)]
final class EmitirGtaTest extends TestCase
{
    private CadastroAgropecuarioEmMemoria $cadastro;
    private AptidaoSanitariaEmMemoria $aptidao;
    private GtaRepositoryEmMemoria $repositorio;
    private EmitirGta $emitirGta;

    protected function setUp(): void
    {
        $this->cadastro = new CadastroAgropecuarioEmMemoria()
            ->comPropriedade('GO000001', saldoBovinos: 100)
            ->comPropriedade('GO000002')
            ->comPropriedade('GO000003', SituacaoPropriedade::Bloqueada, saldoBovinos: 100)
            ->comPropriedade('GO000004', SituacaoPropriedade::Inativa)
            ->comPropriedade('GO000005', saldoBovinos: 100);

        $this->aptidao = new AptidaoSanitariaEmMemoria()
            ->comPendencia('GO000005', 'Sem vacinação válida contra Brucelose.');

        $this->repositorio = new GtaRepositoryEmMemoria();
        $this->emitirGta   = new EmitirGta(
            $this->cadastro,
            $this->aptidao,
            $this->repositorio,
            new RelogioCongelado(),
            new NullLogger(),
        );
    }

    #[Test]
    public function recusaRebanhoSemVacinacaoEmDia(): void
    {
        $this->expectException(RegraEmissaoViolada::class);
        $this->expectExceptionMessage('Rebanho de bovino da origem GO000005 não está apto para transporte: Sem vacinação válida contra Brucelose.');

        try {
            $this->emitirGta->executar($this->comando(origem: 'GO000005'));
        } finally {
            self::assertSame(0, $this->repositorio->total());
        }
    }

    #[Test]
    public function naoConsultaVacinacaoQuandoOCadastroJaImpede(): void
    {
        try {
            $this->emitirGta->executar($this->comando(quantidade: 101));
        } catch (RegraEmissaoViolada) {
        }

        self::assertSame(0, $this->aptidao->consultas, 'Saldo insuficiente barra antes da chamada REST');
    }

    #[Test]
    public function emiteQuandoTodasAsRegrasSaoAtendidas(): void
    {
        $resultado = $this->emitirGta->executar($this->comando());

        self::assertFalse($resultado->repetida);
        self::assertSame('GO000001', $resultado->gta->origem->valor);
        self::assertSame(1, $this->repositorio->total());
    }

    #[Test]
    public function aceitaSaldoExatamenteIgualAQuantidade(): void
    {
        $resultado = $this->emitirGta->executar($this->comando(quantidade: 100));

        self::assertSame(100, $resultado->gta->quantidade);
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function violacoes(): iterable
    {
        yield 'origem inexistente' => ['GO000099', 'GO000002', 10, 'GO000099 não encontrada'];
        yield 'destino inexistente' => ['GO000001', 'GO000099', 10, 'GO000099 não encontrada'];
        yield 'origem bloqueada' => ['GO000003', 'GO000002', 10, 'BLOQUEADA e não pode emitir'];
        yield 'destino inativo' => ['GO000001', 'GO000004', 10, 'INATIVA e não pode receber'];
        yield 'saldo insuficiente' => ['GO000001', 'GO000002', 101, 'disponível 100, solicitado 101'];
    }

    #[Test]
    #[DataProvider('violacoes')]
    public function recusaEmissaoQueViolaRegra(string $origem, string $destino, int $quantidade, string $mensagem): void
    {
        $this->expectException(RegraEmissaoViolada::class);
        $this->expectExceptionMessage($mensagem);

        try {
            $this->emitirGta->executar($this->comando($origem, $destino, $quantidade));
        } finally {
            self::assertSame(0, $this->repositorio->total());
        }
    }

    #[Test]
    public function destinoBloqueadoPodeReceberAnimais(): void
    {
        $resultado = $this->emitirGta->executar($this->comando(destino: 'GO000003'));

        self::assertSame('GO000003', $resultado->gta->destino->valor);
    }

    #[Test]
    public function mesmaChaveEMesmoConteudoDevolveAGtaOriginalSemConsultarOCadastro(): void
    {
        $primeira       = $this->emitirGta->executar($this->comando());
        $consultasAntes = $this->cadastro->consultas;
        $segunda        = $this->emitirGta->executar($this->comando());

        self::assertTrue($segunda->repetida);
        self::assertTrue($primeira->gta->id->equals($segunda->gta->id));
        self::assertSame($consultasAntes, $this->cadastro->consultas);
        self::assertSame(1, $this->repositorio->total());
    }

    #[Test]
    public function mesmaChaveComOutroConteudoLancaConflito(): void
    {
        $this->emitirGta->executar($this->comando(quantidade: 10));

        $this->expectException(ConflitoIdempotencia::class);

        $this->emitirGta->executar($this->comando(quantidade: 20));
    }

    #[Test]
    public function requisicaoConcorrenteComMesmaChaveDevolveAGtaJaGravada(): void
    {
        $comando = $this->comando();
        $gravada = Gta::emitir(
            Uuid::uuid7(),
            new CodigoPropriedade('GO000001'),
            new CodigoPropriedade('GO000002'),
            Especie::Bovino,
            10,
            Finalidade::Abate,
            new RelogioCongelado()->now(),
            ChaveIdempotencia::para($comando->chaveIdempotencia, $comando->impressaoDigital()),
        );
        $this->repositorio->gravadaPorRequisicaoConcorrente = $gravada;

        $resultado = $this->emitirGta->executar($comando);

        self::assertTrue($resultado->repetida);
        self::assertTrue($gravada->id->equals($resultado->gta->id));
    }

    private function comando(
        string $origem = 'GO000001',
        string $destino = 'GO000002',
        int $quantidade = 10,
    ): EmitirGtaComando {
        return new EmitirGtaComando(
            origem: $origem,
            destino: $destino,
            especie: Especie::Bovino,
            quantidade: $quantidade,
            finalidade: Finalidade::Abate,
            chaveIdempotencia: 'pedido-0001',
        );
    }
}
