<?php declare(strict_types=1);

namespace Gta\Http;

use Gta\Domain\Exception\GtaNaoEncontrada;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class IdDaRota
{
    /**
     * Um id que nem é UUID não pode existir: respondemos 404, sem expor detalhes de formato.
     */
    public static function de(ServerRequestInterface $request): UuidInterface
    {
        $id = $request->getAttribute('id');

        if (!is_string($id) || !Uuid::isValid($id)) {
            throw GtaNaoEncontrada::comId(is_string($id) ? $id : '');
        }

        return Uuid::fromString($id);
    }
}
