<?php declare(strict_types=1);

namespace Gta\Integration\Rest;

use Gta\Domain\AptidaoSanitaria;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\IntegracaoIndisponivel;
use Gta\Domain\ResultadoAptidao;
use Gta\Integration\Retry\RetryPolicy;
use Gta\Integration\Retry\TentativasEsgotadas;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Adaptador para a API de vacinação (vacinacao-api, em .NET). Mesma estratégia do adaptador
 * SOAP: timeout, novas tentativas só em falhas transitórias (rede e 5xx) e validação do
 * contrato da resposta antes de devolver um objeto de domínio.
 */
final readonly class RestAptidaoSanitaria implements AptidaoSanitaria
{
    private const string SISTEMA = 'Controle de vacinação (REST)';

    public function __construct(
        private ClientInterface $http,
        private RetryPolicy $retry,
        private LoggerInterface $logger,
    ) {}

    public function verificar(CodigoPropriedade $propriedade, Especie $especie): ResultadoAptidao
    {
        $inicio = hrtime(true);

        try {
            $resposta = $this->retry->executar(
                fn(int $tentativa): ResponseInterface => $this->http->request(
                    'GET',
                    sprintf('propriedades/%s/aptidao', rawurlencode($propriedade->valor)),
                    ['query' => ['especie' => $especie->value], 'headers' => ['Accept' => 'application/json']],
                ),
                fn(Throwable $erro): bool => $this->falhaTransitoria($erro),
            );
        } catch (TentativasEsgotadas $erro) {
            $this->logger->error('Integração REST indisponível', [
                'sistema'    => self::SISTEMA,
                'tentativas' => $erro->tentativas,
                'erro'       => $erro->getPrevious()?->getMessage(),
            ]);

            throw IntegracaoIndisponivel::aposTentativas(self::SISTEMA, $erro->tentativas, $erro);
        } catch (GuzzleException $erro) {
            // 4xx: a requisição foi recusada; repetir não adianta
            throw IntegracaoIndisponivel::respostaInvalida(self::SISTEMA, $erro->getMessage());
        }

        $this->logger->info('Chamada REST concluída', [
            'sistema'     => self::SISTEMA,
            'propriedade' => $propriedade->valor,
            'duracao_ms'  => intdiv(hrtime(true) - $inicio, 1_000_000),
        ]);

        return $this->traduzir((string) $resposta->getBody());
    }

    private function traduzir(string $corpo): ResultadoAptidao
    {
        try {
            $dados = json_decode($corpo, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw IntegracaoIndisponivel::respostaInvalida(self::SISTEMA, 'corpo não é JSON');
        }

        if (!is_array($dados) || !is_bool($dados['apta'] ?? null) || !is_array($dados['pendencias'] ?? null)) {
            throw IntegracaoIndisponivel::respostaInvalida(self::SISTEMA, 'campos "apta" e "pendencias" ausentes ou inválidos');
        }

        $pendencias = array_values(array_filter($dados['pendencias'], is_string(...)));

        return new ResultadoAptidao($dados['apta'], $pendencias);
    }

    private function falhaTransitoria(Throwable $erro): bool
    {
        $transitoria = $erro instanceof ConnectException || $erro instanceof ServerException;

        if ($transitoria) {
            $this->logger->warning('Falha transitória na integração REST', [
                'sistema'  => self::SISTEMA,
                'mensagem' => $erro->getMessage(),
            ]);
        }

        return $transitoria;
    }
}
