<?php declare(strict_types=1);

namespace Gta\Http\Middleware;

use Gta\Domain\Exception\ConflitoIdempotencia;
use Gta\Domain\Exception\DadoInvalido;
use Gta\Domain\Exception\GtaException;
use Gta\Domain\Exception\GtaNaoEncontrada;
use Gta\Domain\Exception\IntegracaoIndisponivel;
use Gta\Domain\Exception\RegraEmissaoViolada;
use Gta\Domain\Exception\TransicaoInvalida;
use Gta\Http\RequisicaoInvalida;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Traduz exceções de domínio e de requisição em respostas Problem Details (RFC 9457).
 * O domínio continua sem conhecer HTTP; o mapeamento fica concentrado aqui.
 */
final readonly class ErrosDeDominioMiddleware implements MiddlewareInterface
{
    private const int RETRY_AFTER_SEGUNDOS = 30;

    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (RequisicaoInvalida $erro) {
            $adicionais = $erro->erros === [] ? [] : ['errors' => $erro->erros];

            return $this->problema($request, $erro->status, $erro->getMessage(), 'requisicao-invalida', $adicionais);
        } catch (GtaException $erro) {
            [$status, $tipo] = $this->classificar($erro);
            $resposta        = $this->problema($request, $status, $erro->getMessage(), $tipo);

            return $erro instanceof IntegracaoIndisponivel
                ? $resposta->withHeader('Retry-After', (string) self::RETRY_AFTER_SEGUNDOS)
                : $resposta;
        }
    }

    /**
     * @return array{int, string}
     */
    private function classificar(GtaException $erro): array
    {
        return match (true) {
            $erro instanceof GtaNaoEncontrada       => [404, 'gta-nao-encontrada'],
            $erro instanceof TransicaoInvalida      => [409, 'transicao-invalida'],
            $erro instanceof ConflitoIdempotencia   => [422, 'conflito-idempotencia'],
            $erro instanceof RegraEmissaoViolada    => [422, 'regra-emissao-violada'],
            $erro instanceof DadoInvalido           => [422, 'dado-invalido'],
            $erro instanceof IntegracaoIndisponivel => [503, 'integracao-indisponivel'],
            default                                 => [500, 'erro-interno'],
        };
    }

    /**
     * @param array<string, mixed> $adicionais
     */
    private function problema(
        ServerRequestInterface $request,
        int $status,
        string $detalhe,
        string $tipo,
        array $adicionais = [],
    ): ResponseInterface {
        return $this->problemDetails->createResponse(
            $request,
            $status,
            $detalhe,
            '',
            '/docs/erros#' . $tipo,
            $adicionais,
        );
    }
}
