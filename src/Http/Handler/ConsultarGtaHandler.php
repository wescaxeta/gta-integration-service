<?php declare(strict_types=1);

namespace Gta\Http\Handler;

use Gta\Domain\Exception\GtaNaoEncontrada;
use Gta\Domain\GtaRepository;
use Gta\Http\GtaJson;
use Gta\Http\IdDaRota;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ConsultarGtaHandler implements RequestHandlerInterface
{
    public function __construct(
        private GtaRepository $repositorio,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id  = IdDaRota::de($request);
        $gta = $this->repositorio->buscar($id) ?? throw GtaNaoEncontrada::comId($id->toString());

        return new JsonResponse(GtaJson::de($gta));
    }
}
