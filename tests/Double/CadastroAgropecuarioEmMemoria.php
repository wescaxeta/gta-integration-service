<?php declare(strict_types=1);

namespace Gta\Tests\Double;

use Gta\Domain\CadastroAgropecuario;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Propriedade;
use Gta\Domain\SituacaoPropriedade;

final class CadastroAgropecuarioEmMemoria implements CadastroAgropecuario
{
    /** @var array<string, Propriedade> */
    private array $propriedades = [];

    /** @var array<string, int> */
    private array $saldos = [];

    public int $consultas = 0;

    public function comPropriedade(
        string $codigo,
        SituacaoPropriedade $situacao = SituacaoPropriedade::Ativa,
        int $saldoBovinos = 0,
    ): self {
        $this->propriedades[$codigo]                          = new Propriedade(new CodigoPropriedade($codigo), 'Propriedade ' . $codigo, 'Goiânia', $situacao);
        $this->saldos[$codigo . ':' . Especie::Bovino->value] = $saldoBovinos;

        return $this;
    }

    public function buscarPropriedade(CodigoPropriedade $codigo): ?Propriedade
    {
        $this->consultas++;

        return $this->propriedades[$codigo->valor] ?? null;
    }

    public function saldoRebanho(CodigoPropriedade $codigo, Especie $especie): int
    {
        $this->consultas++;

        return $this->saldos[$codigo->valor . ':' . $especie->value] ?? 0;
    }
}
