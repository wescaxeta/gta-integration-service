<?php declare(strict_types=1);

namespace Gta\Tests\Integration;

use Gta\Domain\AptidaoSanitaria;
use Gta\Domain\CadastroAgropecuario;
use Gta\Domain\GtaRepository;
use Gta\Http\Handler\CancelarGtaHandler;
use Gta\Http\Handler\ConsultarGtaHandler;
use Gta\Http\Handler\EmitirGtaHandler;
use Gta\Http\Handler\HealthHandler;
use Gta\Http\InputFilter\EmitirGtaInputFilter;
use Gta\Http\Middleware\ErrosDeDominioMiddleware;
use Gta\Http\RequisicaoInvalida;
use Gta\Integration\Retry\RetryPolicy;
use Gta\Integration\Soap\SoapCadastroAgropecuario;
use Gta\Tests\Double\AptidaoSanitariaEmMemoria;
use Gta\Tests\Double\GtaRepositoryEmMemoria;
use Gta\Tests\Double\RelogioCongelado;
use Gta\Tests\Double\SoapClientEmProcesso;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SoapMock\CadastroAgropecuarioMock;

/**
 * Sobe a aplicação Mezzio inteira (pipeline, rotas, container) e faz requisições HTTP
 * em memória. Só o banco é trocado por um repositório em memória; a integração SOAP
 * roda contra o SoapServer real, no mesmo processo.
 */
#[CoversClass(EmitirGtaHandler::class)]
#[CoversClass(ConsultarGtaHandler::class)]
#[CoversClass(CancelarGtaHandler::class)]
#[CoversClass(HealthHandler::class)]
#[CoversClass(EmitirGtaInputFilter::class)]
#[CoversClass(ErrosDeDominioMiddleware::class)]
#[CoversClass(RequisicaoInvalida::class)]
#[RequiresPhpExtension('soap')]
final class ApiTest extends TestCase
{
    private const array CORPO_VALIDO = [
        'origem'     => 'GO000001',
        'destino'    => 'GO000002',
        'especie'    => 'bovino',
        'quantidade' => 50,
        'finalidade' => 'abate',
    ];

    private Application $app;

    protected function setUp(): void
    {
        $raiz      = dirname(__DIR__, 2);
        $container = require $raiz . '/config/container.php';
        self::assertInstanceOf(ServiceManager::class, $container);

        // Serviços já cadastrados (inclusive aliases) têm prioridade na resolução do container.
        $container->setAllowOverride(true);
        $container->setService(GtaRepository::class, new GtaRepositoryEmMemoria());
        $container->setService(ClockInterface::class, new RelogioCongelado());
        $container->setService(LoggerInterface::class, new NullLogger());
        $container->setService(AptidaoSanitaria::class, new AptidaoSanitariaEmMemoria()
            ->comPendencia('GO000005', 'Sem vacinação válida contra Brucelose.'));
        $container->setService(CadastroAgropecuario::class, new SoapCadastroAgropecuario(
            new SoapClientEmProcesso($raiz . '/resources/wsdl/cadastro-agropecuario.wsdl', new CadastroAgropecuarioMock()),
            new RetryPolicy(maxTentativas: 2, esperaInicialMs: 0),
            new NullLogger(),
        ));

        $app = $container->get(Application::class);
        self::assertInstanceOf(Application::class, $app);

        foreach (['pipeline', 'routes'] as $arquivo) {
            $configurar = require $raiz . '/config/' . $arquivo . '.php';
            self::assertIsCallable($configurar);
            $configurar($app);
        }

        $this->app = $app;
    }

    #[Test]
    public function healthCheck(): void
    {
        $resposta = $this->requisitar('GET', '/health');

        self::assertSame(200, $resposta->getStatusCode());
        self::assertSame(['status' => 'ok'], $this->json($resposta));
    }

    #[Test]
    public function emiteGtaEConsultaPeloLinkRetornado(): void
    {
        $emissao = $this->emitir(self::CORPO_VALIDO, 'pedido-0001');

        self::assertSame(201, $emissao->getStatusCode());
        $local = $emissao->getHeaderLine('Location');
        self::assertMatchesRegularExpression('#^/gtas/[0-9a-f-]{36}$#', $local);

        $consulta = $this->requisitar('GET', $local);
        $gta      = $this->json($consulta);

        self::assertSame(200, $consulta->getStatusCode());
        self::assertSame('emitida', $gta['status']);
        self::assertSame(50, $gta['quantidade']);
        self::assertSame('2026-03-15T09:00:00+00:00', $gta['validaAte']);
    }

    #[Test]
    public function repetirAMesmaRequisicaoNaoDuplicaAGta(): void
    {
        $primeira = $this->json($this->emitir(self::CORPO_VALIDO, 'pedido-0001'));
        $segunda  = $this->emitir(self::CORPO_VALIDO, 'pedido-0001');

        self::assertSame(200, $segunda->getStatusCode());
        self::assertSame('true', $segunda->getHeaderLine('Idempotent-Replayed'));
        self::assertSame($primeira['id'], $this->json($segunda)['id']);
    }

    #[Test]
    public function exigeIdempotencyKey(): void
    {
        $resposta = $this->requisitar('POST', '/gtas', self::CORPO_VALIDO);

        self::assertSame(400, $resposta->getStatusCode());
        self::assertSame('application/problem+json', $resposta->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function devolveErrosDeValidacaoPorCampo(): void
    {
        $resposta = $this->emitir(['origem' => 'go1', 'especie' => 'gato', 'quantidade' => 0], 'pedido-0002');
        $problema = $this->json($resposta);

        self::assertSame(422, $resposta->getStatusCode());
        self::assertIsArray($problema['errors']);
        self::assertSame(['origem', 'destino', 'especie', 'finalidade', 'quantidade'], array_keys($problema['errors']));
    }

    #[Test]
    public function regraDeNegocioVioladaRetorna422(): void
    {
        $resposta = $this->emitir(['quantidade' => 9000] + self::CORPO_VALIDO, 'pedido-0003');

        self::assertSame(422, $resposta->getStatusCode());
        self::assertSame('/docs/erros#regra-emissao-violada', $this->json($resposta)['type']);
    }

    #[Test]
    public function rebanhoSemVacinacaoEmDiaRetorna422(): void
    {
        $resposta = $this->emitir(['origem' => 'GO000005'] + self::CORPO_VALIDO, 'pedido-0006');
        $detalhe  = $this->json($resposta)['detail'];

        self::assertSame(422, $resposta->getStatusCode());
        self::assertIsString($detalhe);
        self::assertStringContainsString('não está apto para transporte', $detalhe);
    }

    #[Test]
    public function integracaoForaDoArRetorna503ComRetryAfter(): void
    {
        $resposta = $this->emitir(['origem' => CadastroAgropecuarioMock::CODIGO_INSTAVEL] + self::CORPO_VALIDO, 'pedido-0004');

        self::assertSame(503, $resposta->getStatusCode());
        self::assertSame('30', $resposta->getHeaderLine('Retry-After'));
    }

    #[Test]
    public function cancelaUmaVezERecusaOSegundoCancelamento(): void
    {
        $local = $this->emitir(self::CORPO_VALIDO, 'pedido-0005')->getHeaderLine('Location');

        $primeiro = $this->requisitar('POST', $local . '/cancelamento');
        $segundo  = $this->requisitar('POST', $local . '/cancelamento');

        self::assertSame(200, $primeiro->getStatusCode());
        self::assertSame('cancelada', $this->json($primeiro)['status']);
        self::assertSame(409, $segundo->getStatusCode());
    }

    #[Test]
    public function idInvalidoOuInexistenteRetorna404(): void
    {
        self::assertSame(404, $this->requisitar('GET', '/gtas/nao-e-uuid')->getStatusCode());
        self::assertSame(404, $this->requisitar('GET', '/gtas/0192f0a0-0000-7000-8000-000000000000')->getStatusCode());
    }

    /**
     * @param array<string, mixed> $corpo
     */
    private function emitir(array $corpo, string $chave): ResponseInterface
    {
        return $this->requisitar('POST', '/gtas', $corpo, ['Idempotency-Key' => $chave]);
    }

    /**
     * @param array<string, mixed>|null         $corpo
     * @param array<non-empty-string, string> $headers
     */
    private function requisitar(string $metodo, string $uri, ?array $corpo = null, array $headers = []): ResponseInterface
    {
        $request = new ServerRequest(method: $metodo, uri: $uri, headers: $headers + ['Accept' => 'application/json']);

        if ($corpo !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody(new StreamFactory()->createStream(json_encode($corpo, JSON_THROW_ON_ERROR)));
        }

        return $this->app->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $resposta): array
    {
        $dados = json_decode((string) $resposta->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($dados);

        /** @var array<string, mixed> $dados */
        return $dados;
    }
}
