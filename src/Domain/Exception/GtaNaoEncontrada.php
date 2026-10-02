<?php declare(strict_types=1);

namespace Gta\Domain\Exception;

final class GtaNaoEncontrada extends GtaException
{
    public static function comId(string $id): self
    {
        return new self(sprintf('GTA %s não encontrada.', $id));
    }
}
