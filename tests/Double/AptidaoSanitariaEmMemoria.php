<?php declare(strict_types=1);

namespace Gta\Tests\Double;

use Gta\Domain\AptidaoSanitaria;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\ResultadoAptidao;

final class AptidaoSanitariaEmMemoria implements AptidaoSanitaria
{
    /** @var array<string, list<string>> pendências por código de propriedade */
    private array $inaptas = [];

    public int $consultas = 0;

    public function comPendencia(string $codigo, string $pendencia): self
    {
        $this->inaptas[$codigo][] = $pendencia;

        return $this;
    }

    public function verificar(CodigoPropriedade $propriedade, Especie $especie): ResultadoAptidao
    {
        $this->consultas++;
        $pendencias = $this->inaptas[$propriedade->valor] ?? [];

        return new ResultadoAptidao($pendencias === [], $pendencias);
    }
}
