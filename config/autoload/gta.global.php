<?php declare(strict_types=1);

$env = static fn(string $nome, string $padrao): string => ($valor = getenv($nome)) !== false && $valor !== '' ? $valor : $padrao;

return [
    'gta' => [
        'db' => [
            'dsn'     => $env('DB_DSN', 'pgsql:host=db;port=5432;dbname=gta'),
            'usuario' => $env('DB_USER', 'gta'),
            'senha'   => $env('DB_PASSWORD', 'gta'),
        ],
        'soap' => [
            'wsdl'             => dirname(__DIR__, 2) . '/resources/wsdl/cadastro-agropecuario.wsdl',
            'endpoint'         => $env('SOAP_ENDPOINT', 'http://soap-mock:8081/'),
            'timeout_segundos' => (int) $env('SOAP_TIMEOUT', '5'),
        ],
        'retry' => [
            'max_tentativas'    => (int) $env('RETRY_MAX_TENTATIVAS', '3'),
            'espera_inicial_ms' => (int) $env('RETRY_ESPERA_MS', '200'),
        ],
    ],
    'problem-details' => [
        // Em produção, erros 500 não expõem stack trace.
        'include-throwable-details' => $env('APP_DEBUG', '0') === '1',
    ],
];
