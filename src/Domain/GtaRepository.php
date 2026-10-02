<?php declare(strict_types=1);

namespace Gta\Domain;

use Gta\Domain\Exception\ChaveIdempotenciaJaUtilizada;
use Ramsey\Uuid\UuidInterface;

interface GtaRepository
{
    /**
     * @throws ChaveIdempotenciaJaUtilizada quando outra requisição concorrente gravou a mesma chave
     */
    public function adicionar(Gta $gta): void;

    public function atualizar(Gta $gta): void;

    public function buscar(UuidInterface $id): ?Gta;

    public function buscarPorChaveIdempotencia(string $chave): ?Gta;
}
