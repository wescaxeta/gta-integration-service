<?php declare(strict_types=1);

namespace Gta\Http\Handler;

use Gta\Application\CancelarGta;
use Gta\Http\GtaJson;
use Gta\Http\IdDaRota;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CancelarGtaHandler implements RequestHandlerInterface
{
    public function __construct(
        private CancelarGta $cancelarGta,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(GtaJson::de($this->cancelarGta->executar(IdDaRota::de($request))));
    }
}
