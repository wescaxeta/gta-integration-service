<?php declare(strict_types=1);

namespace Gta\Application;

use Gta\Domain\Gta;

final readonly class ResultadoEmissao
{
    private function __construct(
        public Gta $gta,
        public bool $repetida,
    ) {}

    public static function nova(Gta $gta): self
    {
        return new self($gta, repetida: false);
    }

    /** A mesma requisição já tinha sido processada: devolvemos a GTA original. */
    public static function repetida(Gta $gta): self
    {
        return new self($gta, repetida: true);
    }
}
