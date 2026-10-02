<?php declare(strict_types=1);

namespace SoapMock;

use SoapFault;

/**
 * Implementação fictícia do WebService de cadastro agropecuário, servida via SoapServer.
 * Os dados são inventados; o código GO999999 simula o serviço fora do ar.
 */
final class CadastroAgropecuarioMock
{
    public const string CODIGO_INSTAVEL = 'GO999999';

    private const array PROPRIEDADES = [
        'GO000001' => ['nome' => 'Fazenda Boa Vista', 'municipio' => 'Rio Verde', 'uf' => 'GO', 'situacao' => 'ATIVA'],
        'GO000002' => ['nome' => 'Frigorífico Central', 'municipio' => 'Goiânia', 'uf' => 'GO', 'situacao' => 'ATIVA'],
        'GO000003' => ['nome' => 'Sítio Santa Luzia', 'municipio' => 'Jataí', 'uf' => 'GO', 'situacao' => 'BLOQUEADA'],
        'GO000004' => ['nome' => 'Fazenda Desativada', 'municipio' => 'Anápolis', 'uf' => 'GO', 'situacao' => 'INATIVA'],
        'MT000010' => ['nome' => 'Fazenda Pantanal', 'municipio' => 'Cáceres', 'uf' => 'MT', 'situacao' => 'ATIVA'],
    ];

    private const array REBANHOS = [
        'GO000001' => ['bovino' => 500, 'suino' => 120],
        'GO000003' => ['bovino' => 80],
        'MT000010' => ['bovino' => 1200, 'equino' => 15],
    ];

    /**
     * @return array{encontrada: bool, propriedade?: array<string, string>}
     */
    public function ConsultarPropriedade(object $parametros): array
    {
        $codigo = $this->texto($parametros, 'codigo');
        $this->simularInstabilidade($codigo);

        if (!isset(self::PROPRIEDADES[$codigo])) {
            return ['encontrada' => false];
        }

        return [
            'encontrada'  => true,
            'propriedade' => ['codigo' => $codigo] + self::PROPRIEDADES[$codigo],
        ];
    }

    /**
     * @return array{saldo: int}
     */
    public function ConsultarSaldoRebanho(object $parametros): array
    {
        $codigo  = $this->texto($parametros, 'codigo');
        $especie = $this->texto($parametros, 'especie');
        $this->simularInstabilidade($codigo);

        return ['saldo' => self::REBANHOS[$codigo][$especie] ?? 0];
    }

    private function simularInstabilidade(string $codigo): void
    {
        if ($codigo === self::CODIGO_INSTAVEL) {
            throw new SoapFault('Server', 'Serviço temporariamente indisponível.');
        }
    }

    private function texto(object $parametros, string $campo): string
    {
        $valor = get_object_vars($parametros)[$campo] ?? null;

        if (!is_string($valor)) {
            throw new SoapFault('Client', sprintf('Parâmetro obrigatório ausente: %s.', $campo));
        }

        return $valor;
    }
}
