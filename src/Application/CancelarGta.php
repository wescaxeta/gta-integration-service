<?php declare(strict_types=1);

namespace Gta\Application;

use Gta\Domain\Exception\GtaNaoEncontrada;
use Gta\Domain\Gta;
use Gta\Domain\GtaRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

final readonly class CancelarGta
{
    public function __construct(
        private GtaRepository $repositorio,
        private ClockInterface $relogio,
        private LoggerInterface $logger,
    ) {}

    public function executar(UuidInterface $id): Gta
    {
        $gta = $this->repositorio->buscar($id) ?? throw GtaNaoEncontrada::comId($id->toString());

        $gta->cancelar($this->relogio->now());
        $this->repositorio->atualizar($gta);

        $this->logger->info('GTA cancelada', ['gta_id' => $id->toString()]);

        return $gta;
    }
}
