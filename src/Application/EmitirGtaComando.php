<?php declare(strict_types=1);

namespace Gta\Application;

use Gta\Domain\Especie;
use Gta\Domain\Finalidade;

/**
 * Dados de entrada da emissão, já validados quanto ao formato pela camada HTTP.
 */
final readonly class EmitirGtaComando
{
    public function __construct(
        public string $origem,
        public string $destino,
        public Especie $especie,
        public int $quantidade,
        public Finalidade $finalidade,
        public string $chaveIdempotencia,
    ) {}

    /**
     * Conteúdo que identifica a requisição para fins de idempotência.
     *
     * @return array<string, scalar>
     */
    public function impressaoDigital(): array
    {
        return [
            'origem'     => strtoupper($this->origem),
            'destino'    => strtoupper($this->destino),
            'especie'    => $this->especie->value,
            'quantidade' => $this->quantidade,
            'finalidade' => $this->finalidade->value,
        ];
    }
}
