<?php declare(strict_types=1);

namespace Gta\Tests\Integration;

use ArrayObject;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\IntegracaoIndisponivel;
use Gta\Domain\ResultadoAptidao;
use Gta\Integration\Rest\RestAptidaoSanitaria;
use Gta\Integration\Retry\RetryPolicy;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

/**
 * Exercita o adaptador com o Guzzle de verdade, trocando apenas a camada de transporte
 * por respostas HTTP programadas (MockHandler).
 */
#[CoversClass(RestAptidaoSanitaria::class)]
#[CoversClass(ResultadoAptidao::class)]
final class RestAptidaoSanitariaTest extends TestCase
{
    /** @var ArrayObject<int, array<mixed>> requisições enviadas, registradas pelo Guzzle */
    private ArrayObject $historico;

    protected function setUp(): void
    {
        $this->historico = new ArrayObject();
    }

    #[Test]
    public function montaARequisicaoETraduzRebanhoApto(): void
    {
        $resultado = $this->adaptador(new Response(200, [], (string) json_encode(['apta' => true, 'pendencias' => []])))
            ->verificar(new CodigoPropriedade('GO000001'), Especie::Bovino);

        self::assertTrue($resultado->apta);
        self::assertSame(
            'http://vacinacao.test/propriedades/GO000001/aptidao?especie=bovino',
            (string) $this->primeiraRequisicao()->getUri(),
        );
    }

    #[Test]
    public function traduzRebanhoInaptoComPendencias(): void
    {
        $corpo = ['apta' => false, 'pendencias' => ['Sem vacinação válida contra Brucelose.'], 'avisos' => []];

        $resultado = $this->adaptador(new Response(200, [], (string) json_encode($corpo)))
            ->verificar(new CodigoPropriedade('GO000005'), Especie::Bovino);

        self::assertFalse($resultado->apta);
        self::assertSame(['Sem vacinação válida contra Brucelose.'], $resultado->pendencias);
    }

    #[Test]
    public function repeteApos503ESeRecupera(): void
    {
        $resultado = $this->adaptador(
            new Response(503),
            new Response(200, [], '{"apta":true,"pendencias":[]}'),
        )->verificar(new CodigoPropriedade('GO000001'), Especie::Bovino);

        self::assertTrue($resultado->apta);
        self::assertCount(2, $this->historico);
    }

    #[Test]
    public function servicoForaDoArViraIndisponibilidadeAposAsTentativas(): void
    {
        $falha = new ConnectException('Connection refused', new Request('GET', 'propriedades/GO000001/aptidao'));

        $this->expectException(IntegracaoIndisponivel::class);
        $this->expectExceptionMessage('Controle de vacinação (REST) indisponível após 3 tentativa(s)');

        $this->adaptador($falha, $falha, $falha)->verificar(new CodigoPropriedade('GO000001'), Especie::Bovino);
    }

    #[Test]
    public function erro4xxNaoERepetido(): void
    {
        try {
            $this->adaptador(new Response(400), new Response(200))->verificar(new CodigoPropriedade('GO000001'), Especie::Bovino);
            self::fail('Deveria lançar IntegracaoIndisponivel.');
        } catch (IntegracaoIndisponivel $erro) {
            self::assertStringContainsString('fora do contrato', $erro->getMessage());
            self::assertCount(1, $this->historico);
        }
    }

    #[Test]
    public function respostaForaDoContratoERejeitada(): void
    {
        $this->expectException(IntegracaoIndisponivel::class);
        $this->expectExceptionMessage('campos "apta" e "pendencias"');

        $this->adaptador(new Response(200, [], '{"status":"ok"}'))->verificar(new CodigoPropriedade('GO000001'), Especie::Bovino);
    }

    private function primeiraRequisicao(): RequestInterface
    {
        $requisicao = ($this->historico[0] ?? [])['request'] ?? null;
        self::assertInstanceOf(RequestInterface::class, $requisicao);

        return $requisicao;
    }

    private function adaptador(Response|ConnectException ...$respostas): RestAptidaoSanitaria
    {
        $historico = $this->historico;
        $pilha     = HandlerStack::create(new MockHandler(array_values($respostas)));
        $pilha->push(Middleware::history($historico));

        return new RestAptidaoSanitaria(
            new Client(['handler' => $pilha, 'base_uri' => 'http://vacinacao.test/']),
            new RetryPolicy(maxTentativas: 3, esperaInicialMs: 0),
            new NullLogger(),
        );
    }
}
