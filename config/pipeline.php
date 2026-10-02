<?php declare(strict_types=1);

use Gta\Http\Middleware\ErrosDeDominioMiddleware;
use Mezzio\Application;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\ProblemDetails\ProblemDetailsMiddleware;
use Mezzio\ProblemDetails\ProblemDetailsNotFoundHandler;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;

return static function (Application $app): void {
    // Qualquer erro não tratado vira Problem Details (500), nunca HTML ou stack trace.
    $app->pipe(ProblemDetailsMiddleware::class);
    $app->pipe(ErrosDeDominioMiddleware::class);

    $app->pipe(RouteMiddleware::class);
    $app->pipe(ImplicitHeadMiddleware::class);
    $app->pipe(ImplicitOptionsMiddleware::class);
    $app->pipe(MethodNotAllowedMiddleware::class);
    $app->pipe(BodyParamsMiddleware::class);
    $app->pipe(DispatchMiddleware::class);

    $app->pipe(ProblemDetailsNotFoundHandler::class);
};
