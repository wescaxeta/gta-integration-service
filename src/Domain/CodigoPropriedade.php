<?php declare(strict_types=1);

namespace Gta\Domain;

use Gta\Domain\Exception\DadoInvalido;
use Stringable;

/**
 * Código estadual da propriedade rural: sigla da UF + 6 dígitos (ex.: GO000123).
 */
final readonly class CodigoPropriedade implements Stringable
{
    private const string FORMATO = '/^[A-Z]{2}\d{6}$/';

    public string $valor;

    public function __construct(string $valor)
    {
        $valor = strtoupper(trim($valor));

        if (preg_match(self::FORMATO, $valor) !== 1) {
            throw DadoInvalido::codigoPropriedade($valor);
        }

        $this->valor = $valor;
    }

    public function uf(): string
    {
        return substr($this->valor, 0, 2);
    }

    public function igual(self $outro): bool
    {
        return $this->valor === $outro->valor;
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
