<?php declare(strict_types=1);

namespace Gta\Tests\Double;

use Gta\Domain\Exception\ChaveIdempotenciaJaUtilizada;
use Gta\Domain\Gta;
use Gta\Domain\GtaRepository;
use Ramsey\Uuid\UuidInterface;

final class GtaRepositoryEmMemoria implements GtaRepository
{
    /** @var array<string, Gta> */
    private array $gtas = [];

    /**
     * Simula outra requisição concorrente gravando a mesma chave entre a
     * verificação de idempotência e o INSERT.
     */
    public ?Gta $gravadaPorRequisicaoConcorrente = null;

    public function adicionar(Gta $gta): void
    {
        if ($this->gravadaPorRequisicaoConcorrente !== null) {
            $this->gtas[$this->gravadaPorRequisicaoConcorrente->id->toString()] = $this->gravadaPorRequisicaoConcorrente;
            $this->gravadaPorRequisicaoConcorrente                              = null;
        }

        if ($this->buscarPorChaveIdempotencia($gta->chaveIdempotencia->chave) !== null) {
            throw ChaveIdempotenciaJaUtilizada::chave($gta->chaveIdempotencia->chave);
        }

        $this->gtas[$gta->id->toString()] = $gta;
    }

    public function atualizar(Gta $gta): void
    {
        $this->gtas[$gta->id->toString()] = $gta;
    }

    public function buscar(UuidInterface $id): ?Gta
    {
        return $this->gtas[$id->toString()] ?? null;
    }

    public function buscarPorChaveIdempotencia(string $chave): ?Gta
    {
        foreach ($this->gtas as $gta) {
            if ($gta->chaveIdempotencia->chave === $chave) {
                return $gta;
            }
        }

        return null;
    }

    public function total(): int
    {
        return count($this->gtas);
    }
}
