<?php declare(strict_types=1);

namespace Gta\Domain;

use Gta\Domain\Exception\IntegracaoIndisponivel;

/**
 * Porta para o sistema de controle de vacinação: o rebanho só pode ser transportado
 * com as vacinas obrigatórias em dia. O domínio não sabe que, do outro lado, existe
 * uma API REST em .NET.
 */
interface AptidaoSanitaria
{
    /**
     * @throws IntegracaoIndisponivel
     */
    public function verificar(CodigoPropriedade $propriedade, Especie $especie): ResultadoAptidao;
}
