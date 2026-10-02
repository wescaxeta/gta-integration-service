<?php declare(strict_types=1);

namespace Gta\Tests\Integration;

use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\IntegracaoIndisponivel;
use Gta\Domain\SituacaoPropriedade;
use Gta\Integration\Retry\RetryPolicy;
use Gta\Integration\Soap\RespostaSoap;
use Gta\Integration\Soap\SoapCadastroAgropecuario;
use Gta\Tests\Double\ServicoSoapComContratoQuebrado;
use Gta\Tests\Double\SoapClientEmProcesso;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SoapMock\CadastroAgropecuarioMock;

/**
 * Exercita o adaptador contra o SoapServer real (WSDL + envelope XML), sem rede.
 */
#[CoversClass(SoapCadastroAgropecuario::class)]
#[CoversClass(RespostaSoap::class)]
#[CoversClass(IntegracaoIndisponivel::class)]
#[RequiresPhpExtension('soap')]
final class SoapCadastroAgropecuarioTest extends TestCase
{
    private const string WSDL = __DIR__ . '/../../resources/wsdl/cadastro-agropecuario.wsdl';

    #[Test]
    public function traduzPropriedadeDoContratoSoapParaODominio(): void
    {
        $propriedade = $this->adaptador()->buscarPropriedade(new CodigoPropriedade('GO000003'));

        self::assertNotNull($propriedade);
        self::assertSame('Sítio Santa Luzia', $propriedade->nome);
        self::assertSame('Jataí', $propriedade->municipio);
        self::assertSame(SituacaoPropriedade::Bloqueada, $propriedade->situacao);
    }

    #[Test]
    public function propriedadeInexistenteRetornaNull(): void
    {
        self::assertNull($this->adaptador()->buscarPropriedade(new CodigoPropriedade('GO123456')));
    }

    #[Test]
    public function consultaSaldoDoRebanhoPorEspecie(): void
    {
        $codigo = new CodigoPropriedade('GO000001');

        self::assertSame(500, $this->adaptador()->saldoRebanho($codigo, Especie::Bovino));
        self::assertSame(0, $this->adaptador()->saldoRebanho($codigo, Especie::Caprino));
    }

    #[Test]
    public function repeteAposFalhaDeTransporteESeRecupera(): void
    {
        $cliente = new SoapClientEmProcesso(self::WSDL, new CadastroAgropecuarioMock(), falhasDeTransporte: 2);

        $propriedade = $this->adaptador($cliente)->buscarPropriedade(new CodigoPropriedade('GO000001'));

        self::assertNotNull($propriedade);
        self::assertSame(3, $cliente->requisicoes);
    }

    #[Test]
    public function faultDoServidorEsgotaAsTentativasEViraIndisponibilidade(): void
    {
        $cliente = new SoapClientEmProcesso(self::WSDL, new CadastroAgropecuarioMock());

        try {
            $this->adaptador($cliente)->buscarPropriedade(new CodigoPropriedade(CadastroAgropecuarioMock::CODIGO_INSTAVEL));
            self::fail('Deveria lançar IntegracaoIndisponivel.');
        } catch (IntegracaoIndisponivel $erro) {
            self::assertStringContainsString('após 3 tentativa(s)', $erro->getMessage());
            self::assertSame(3, $cliente->requisicoes);
        }
    }

    #[Test]
    public function respostaForaDoContratoEhRejeitada(): void
    {
        $cliente = new SoapClientEmProcesso(self::WSDL, new ServicoSoapComContratoQuebrado());

        $this->expectException(IntegracaoIndisponivel::class);
        $this->expectExceptionMessage('situação de propriedade desconhecida');

        $this->adaptador($cliente)->buscarPropriedade(new CodigoPropriedade('GO000001'));
    }

    private function adaptador(?SoapClientEmProcesso $cliente = null): SoapCadastroAgropecuario
    {
        return new SoapCadastroAgropecuario(
            $cliente ?? new SoapClientEmProcesso(self::WSDL, new CadastroAgropecuarioMock()),
            new RetryPolicy(maxTentativas: 3, esperaInicialMs: 0),
            new NullLogger(),
        );
    }
}
