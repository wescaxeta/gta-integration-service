<?php declare(strict_types=1);

namespace Gta\Domain\Exception;

use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\SituacaoPropriedade;

final class RegraEmissaoViolada extends GtaException
{
    public static function propriedadeInexistente(CodigoPropriedade $codigo): self
    {
        return new self(sprintf('Propriedade %s não encontrada no cadastro agropecuário.', $codigo));
    }

    public static function origemImpedida(CodigoPropriedade $codigo, SituacaoPropriedade $situacao): self
    {
        return new self(sprintf(
            'Propriedade de origem %s está com situação %s e não pode emitir GTA.',
            $codigo,
            $situacao->value,
        ));
    }

    public static function destinoImpedido(CodigoPropriedade $codigo, SituacaoPropriedade $situacao): self
    {
        return new self(sprintf(
            'Propriedade de destino %s está com situação %s e não pode receber animais.',
            $codigo,
            $situacao->value,
        ));
    }

    /**
     * @param list<string> $pendencias
     */
    public static function rebanhoInapto(CodigoPropriedade $codigo, Especie $especie, array $pendencias): self
    {
        $motivo = $pendencias === [] ? 'vacinação obrigatória pendente' : implode(' ', $pendencias);

        return new self(sprintf(
            'Rebanho de %s da origem %s não está apto para transporte: %s',
            $especie->value,
            $codigo,
            $motivo,
        ));
    }

    public static function saldoInsuficiente(Especie $especie, int $saldo, int $solicitado): self
    {
        return new self(sprintf(
            'Saldo insuficiente de %s na origem: disponível %d, solicitado %d.',
            $especie->value,
            $saldo,
            $solicitado,
        ));
    }
}
