<?php declare(strict_types=1);

namespace Gta\Domain\Exception;

final class DadoInvalido extends GtaException
{
    public static function codigoPropriedade(string $valor): self
    {
        return new self(sprintf('Código de propriedade inválido: "%s". Formato esperado: UF + 6 dígitos (ex.: GO000123).', $valor));
    }

    public static function origemIgualDestino(): self
    {
        return new self('Propriedade de origem e de destino devem ser diferentes.');
    }

    public static function quantidade(int $quantidade): self
    {
        return new self(sprintf('Quantidade de animais deve ser maior que zero (recebido: %d).', $quantidade));
    }

    public static function chaveIdempotencia(): self
    {
        return new self('Idempotency-Key deve ter de 8 a 64 caracteres (letras, números, "-" ou "_").');
    }
}
