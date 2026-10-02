<?php declare(strict_types=1);

namespace Gta\Domain;

final readonly class ResultadoAptidao
{
    /**
     * @param list<string> $pendencias motivos que impedem o transporte (vazio quando apto)
     */
    public function __construct(
        public bool $apta,
        public array $pendencias = [],
    ) {}
}
