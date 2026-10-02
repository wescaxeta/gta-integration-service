<?php declare(strict_types=1);

namespace Gta\Integration\Retry;

use RuntimeException;
use Throwable;

final class TentativasEsgotadas extends RuntimeException
{
    public function __construct(
        public readonly int $tentativas,
        Throwable $ultimaFalha,
    ) {
        parent::__construct(
            sprintf('Operação falhou após %d tentativa(s): %s', $tentativas, $ultimaFalha->getMessage()),
            previous: $ultimaFalha,
        );
    }
}
