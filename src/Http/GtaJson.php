<?php declare(strict_types=1);

namespace Gta\Http;

use DateTimeInterface;
use Gta\Domain\Gta;

/**
 * Representação pública da GTA na API. Campos internos (hash da requisição) ficam de fora.
 */
final class GtaJson
{
    /**
     * @return array<string, mixed>
     */
    public static function de(Gta $gta): array
    {
        return [
            'id'          => $gta->id->toString(),
            'status'      => $gta->status->value,
            'origem'      => $gta->origem->valor,
            'destino'     => $gta->destino->valor,
            'especie'     => $gta->especie->value,
            'quantidade'  => $gta->quantidade,
            'finalidade'  => $gta->finalidade->value,
            'emitidaEm'   => $gta->emitidaEm->format(DateTimeInterface::ATOM),
            'validaAte'   => $gta->validaAte->format(DateTimeInterface::ATOM),
            'canceladaEm' => $gta->canceladaEm?->format(DateTimeInterface::ATOM),
            '_links'      => [
                'self'         => ['href' => '/gtas/' . $gta->id->toString()],
                'cancelamento' => ['href' => '/gtas/' . $gta->id->toString() . '/cancelamento'],
            ],
        ];
    }
}
