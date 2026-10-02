<?php declare(strict_types=1);

namespace Gta\Tests\Double;

/**
 * Serviço SOAP que devolve uma situação de propriedade fora do contrato combinado.
 */
final class ServicoSoapComContratoQuebrado
{
    /**
     * @return array<string, mixed>
     */
    public function ConsultarPropriedade(object $parametros): array
    {
        return [
            'encontrada'  => true,
            'propriedade' => [
                'codigo'    => 'GO000001',
                'nome'      => 'Fazenda',
                'municipio' => 'Rio Verde',
                'uf'        => 'GO',
                'situacao'  => 'EM_ANALISE',
            ],
        ];
    }
}
