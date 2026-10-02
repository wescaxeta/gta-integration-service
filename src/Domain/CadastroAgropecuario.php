<?php declare(strict_types=1);

namespace Gta\Domain;

use Gta\Domain\Exception\IntegracaoIndisponivel;

/**
 * Porta para o sistema estadual de cadastro agropecuário (propriedades e rebanhos).
 * O domínio não sabe que, do outro lado, existe um WebService SOAP.
 */
interface CadastroAgropecuario
{
    /**
     * @throws IntegracaoIndisponivel
     */
    public function buscarPropriedade(CodigoPropriedade $codigo): ?Propriedade;

    /**
     * @throws IntegracaoIndisponivel
     */
    public function saldoRebanho(CodigoPropriedade $codigo, Especie $especie): int;
}
