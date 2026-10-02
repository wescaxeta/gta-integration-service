<?php declare(strict_types=1);

namespace Gta\Domain\Exception;

final class TransicaoInvalida extends GtaException
{
    public static function jaCancelada(string $id): self
    {
        return new self(sprintf('GTA %s já está cancelada.', $id));
    }

    public static function vencida(string $id): self
    {
        return new self(sprintf('GTA %s está vencida e não pode mais ser cancelada.', $id));
    }
}
