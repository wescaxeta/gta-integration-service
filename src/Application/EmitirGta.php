<?php declare(strict_types=1);

namespace Gta\Application;

use Gta\Domain\AptidaoSanitaria;
use Gta\Domain\CadastroAgropecuario;
use Gta\Domain\ChaveIdempotencia;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Exception\ChaveIdempotenciaJaUtilizada;
use Gta\Domain\Exception\ConflitoIdempotencia;
use Gta\Domain\Exception\RegraEmissaoViolada;
use Gta\Domain\Gta;
use Gta\Domain\GtaRepository;
use Gta\Domain\Propriedade;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final readonly class EmitirGta
{
    public function __construct(
        private CadastroAgropecuario $cadastro,
        private AptidaoSanitaria $aptidaoSanitaria,
        private GtaRepository $repositorio,
        private ClockInterface $relogio,
        private LoggerInterface $logger,
    ) {}

    public function executar(EmitirGtaComando $comando): ResultadoEmissao
    {
        $chave = ChaveIdempotencia::para($comando->chaveIdempotencia, $comando->impressaoDigital());

        $existente = $this->gtaJaEmitida($chave);
        if ($existente !== null) {
            return ResultadoEmissao::repetida($existente);
        }

        $origem  = new CodigoPropriedade($comando->origem);
        $destino = new CodigoPropriedade($comando->destino);

        $this->validarOrigem($origem, $comando);
        $this->validarDestino($destino);

        $gta = Gta::emitir(
            id: Uuid::uuid7(),
            origem: $origem,
            destino: $destino,
            especie: $comando->especie,
            quantidade: $comando->quantidade,
            finalidade: $comando->finalidade,
            agora: $this->relogio->now(),
            chaveIdempotencia: $chave,
        );

        try {
            $this->repositorio->adicionar($gta);
        } catch (ChaveIdempotenciaJaUtilizada) {
            // Requisição concorrente com a mesma chave gravou primeiro.
            return ResultadoEmissao::repetida($this->gtaJaEmitida($chave) ?? throw ConflitoIdempotencia::conteudoDiferente($chave->chave));
        }

        $this->logger->info('GTA emitida', [
            'gta_id'     => $gta->id->toString(),
            'origem'     => $origem->valor,
            'destino'    => $destino->valor,
            'especie'    => $gta->especie->value,
            'quantidade' => $gta->quantidade,
        ]);

        return ResultadoEmissao::nova($gta);
    }

    private function gtaJaEmitida(ChaveIdempotencia $chave): ?Gta
    {
        $existente = $this->repositorio->buscarPorChaveIdempotencia($chave->chave);

        if ($existente === null) {
            return null;
        }

        if (!$existente->chaveIdempotencia->mesmaRequisicao($chave)) {
            throw ConflitoIdempotencia::conteudoDiferente($chave->chave);
        }

        return $existente;
    }

    private function validarOrigem(CodigoPropriedade $codigo, EmitirGtaComando $comando): void
    {
        $origem = $this->propriedadeExistente($codigo);

        if (!$origem->podeEnviarAnimais()) {
            throw RegraEmissaoViolada::origemImpedida($codigo, $origem->situacao);
        }

        $saldo = $this->cadastro->saldoRebanho($codigo, $comando->especie);
        if ($saldo < $comando->quantidade) {
            throw RegraEmissaoViolada::saldoInsuficiente($comando->especie, $saldo, $comando->quantidade);
        }

        $aptidao = $this->aptidaoSanitaria->verificar($codigo, $comando->especie);
        if (!$aptidao->apta) {
            throw RegraEmissaoViolada::rebanhoInapto($codigo, $comando->especie, $aptidao->pendencias);
        }
    }

    private function validarDestino(CodigoPropriedade $codigo): void
    {
        $destino = $this->propriedadeExistente($codigo);

        if (!$destino->podeReceberAnimais()) {
            throw RegraEmissaoViolada::destinoImpedido($codigo, $destino->situacao);
        }
    }

    private function propriedadeExistente(CodigoPropriedade $codigo): Propriedade
    {
        return $this->cadastro->buscarPropriedade($codigo)
            ?? throw RegraEmissaoViolada::propriedadeInexistente($codigo);
    }
}
