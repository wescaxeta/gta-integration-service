<?php declare(strict_types=1);

namespace Gta\Integration\Soap;

use Gta\Domain\CadastroAgropecuario;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\IntegracaoIndisponivel;
use Gta\Domain\Propriedade;
use Gta\Domain\SituacaoPropriedade;
use Gta\Integration\Retry\RetryPolicy;
use Gta\Integration\Retry\TentativasEsgotadas;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Adaptador (camada anticorrupção) entre o domínio e o WebService SOAP do cadastro
 * agropecuário estadual. Traduz o contrato externo para objetos de domínio e isola
 * o restante da aplicação de falhas de rede, timeouts e mudanças de contrato.
 */
final readonly class SoapCadastroAgropecuario implements CadastroAgropecuario
{
    private const string SISTEMA = 'Cadastro agropecuário (SOAP)';

    public function __construct(
        private SoapClient $cliente,
        private RetryPolicy $retry,
        private LoggerInterface $logger,
    ) {}

    public function buscarPropriedade(CodigoPropriedade $codigo): ?Propriedade
    {
        $resposta = $this->chamar('ConsultarPropriedade', ['codigo' => $codigo->valor]);

        if (!RespostaSoap::bool($resposta, 'encontrada')) {
            return null;
        }

        $dados    = RespostaSoap::objeto($resposta, 'propriedade');
        $situacao = SituacaoPropriedade::tryFrom(RespostaSoap::string($dados, 'situacao'))
            ?? throw IntegracaoIndisponivel::respostaInvalida(self::SISTEMA, 'situação de propriedade desconhecida');

        return new Propriedade(
            codigo: new CodigoPropriedade(RespostaSoap::string($dados, 'codigo')),
            nome: RespostaSoap::string($dados, 'nome'),
            municipio: RespostaSoap::string($dados, 'municipio'),
            situacao: $situacao,
        );
    }

    public function saldoRebanho(CodigoPropriedade $codigo, Especie $especie): int
    {
        $resposta = $this->chamar('ConsultarSaldoRebanho', [
            'codigo'  => $codigo->valor,
            'especie' => $especie->value,
        ]);

        return RespostaSoap::int($resposta, 'saldo');
    }

    /**
     * @param array<string, string> $parametros
     */
    private function chamar(string $operacao, array $parametros): object
    {
        $inicio = hrtime(true);

        try {
            $resposta = $this->retry->executar(
                fn(int $tentativa): mixed => $this->cliente->__soapCall($operacao, [$parametros]),
                fn(Throwable $erro): bool => $this->falhaTransitoria($operacao, $erro),
            );
        } catch (TentativasEsgotadas $erro) {
            $this->logger->error('Integração SOAP indisponível', [
                'operacao'   => $operacao,
                'tentativas' => $erro->tentativas,
                'erro'       => $erro->getPrevious()?->getMessage(),
            ]);

            throw IntegracaoIndisponivel::aposTentativas(self::SISTEMA, $erro->tentativas, $erro);
        } catch (SoapFault $erro) {
            // Falha não transitória (ex.: requisição rejeitada pelo serviço): repetir não adianta.
            throw IntegracaoIndisponivel::respostaInvalida(self::SISTEMA, $erro->getMessage());
        }

        $this->logger->info('Chamada SOAP concluída', [
            'operacao'   => $operacao,
            'duracao_ms' => intdiv(hrtime(true) - $inicio, 1_000_000),
        ]);

        return is_object($resposta)
            ? $resposta
            : throw IntegracaoIndisponivel::respostaInvalida(self::SISTEMA, 'corpo da resposta vazio');
    }

    /**
     * "HTTP" indica falha de transporte (timeout, conexão recusada) e "Server" indica erro
     * interno do serviço; ambas podem passar sozinhas. Faults "Client" significam requisição
     * inválida e não são repetidas.
     */
    private function falhaTransitoria(string $operacao, Throwable $erro): bool
    {
        if (!$erro instanceof SoapFault) {
            return false;
        }

        $codigo      = (string) $erro->faultcode;
        $transitoria = $codigo === 'HTTP' || str_ends_with($codigo, 'Server');

        if ($transitoria) {
            $this->logger->warning('Falha transitória na integração SOAP', [
                'operacao' => $operacao,
                'fault'    => $codigo,
                'mensagem' => $erro->getMessage(),
            ]);
        }

        return $transitoria;
    }
}
