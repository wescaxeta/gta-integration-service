<?php declare(strict_types=1);

use Gta\Http\Handler\CancelarGtaHandler;
use Gta\Http\Handler\ConsultarGtaHandler;
use Gta\Http\Handler\EmitirGtaHandler;
use Gta\Http\Handler\HealthHandler;
use Mezzio\Application;

return static function (Application $app): void {
    $app->get('/health', HealthHandler::class, 'health');
    $app->post('/gtas', EmitirGtaHandler::class, 'gta.emitir');
    $app->get('/gtas/{id}', ConsultarGtaHandler::class, 'gta.consultar');
    $app->post('/gtas/{id}/cancelamento', CancelarGtaHandler::class, 'gta.cancelar');
};
