<?php declare(strict_types=1);

namespace Gta\Tests\Double;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class RelogioCongelado implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $agora = new DateTimeImmutable('2026-03-10 09:00:00+00:00'),
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->agora;
    }

    public function avancar(string $intervalo): void
    {
        $this->agora = $this->agora->modify($intervalo);
    }
}
