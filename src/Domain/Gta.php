<?php declare(strict_types=1);

namespace Gta\Domain;

use DateTimeImmutable;
use Gta\Domain\Exception\DadoInvalido;
use Gta\Domain\Exception\TransicaoInvalida;
use Ramsey\Uuid\UuidInterface;

/**
 * Guia de Trânsito Animal: documento obrigatório para movimentar animais entre propriedades.
 */
final class Gta
{
    /** Simplificação: na prática a validade depende da distância e do meio de transporte. */
    public const int VALIDADE_DIAS = 5;

    public private(set) StatusGta $status;

    public private(set) ?DateTimeImmutable $canceladaEm;

    private function __construct(
        public readonly UuidInterface $id,
        public readonly CodigoPropriedade $origem,
        public readonly CodigoPropriedade $destino,
        public readonly Especie $especie,
        public readonly int $quantidade,
        public readonly Finalidade $finalidade,
        public readonly DateTimeImmutable $emitidaEm,
        public readonly DateTimeImmutable $validaAte,
        public readonly ChaveIdempotencia $chaveIdempotencia,
        StatusGta $status,
        ?DateTimeImmutable $canceladaEm,
    ) {
        $this->status      = $status;
        $this->canceladaEm = $canceladaEm;
    }

    public static function emitir(
        UuidInterface $id,
        CodigoPropriedade $origem,
        CodigoPropriedade $destino,
        Especie $especie,
        int $quantidade,
        Finalidade $finalidade,
        DateTimeImmutable $agora,
        ChaveIdempotencia $chaveIdempotencia,
    ): self {
        if ($origem->igual($destino)) {
            throw DadoInvalido::origemIgualDestino();
        }

        if ($quantidade <= 0) {
            throw DadoInvalido::quantidade($quantidade);
        }

        return new self(
            id: $id,
            origem: $origem,
            destino: $destino,
            especie: $especie,
            quantidade: $quantidade,
            finalidade: $finalidade,
            emitidaEm: $agora,
            validaAte: $agora->modify(sprintf('+%d days', self::VALIDADE_DIAS)),
            chaveIdempotencia: $chaveIdempotencia,
            status: StatusGta::Emitida,
            canceladaEm: null,
        );
    }

    /**
     * Recria uma GTA já persistida, sem reaplicar as regras de emissão.
     */
    public static function reconstituir(
        UuidInterface $id,
        CodigoPropriedade $origem,
        CodigoPropriedade $destino,
        Especie $especie,
        int $quantidade,
        Finalidade $finalidade,
        DateTimeImmutable $emitidaEm,
        DateTimeImmutable $validaAte,
        ChaveIdempotencia $chaveIdempotencia,
        StatusGta $status,
        ?DateTimeImmutable $canceladaEm,
    ): self {
        return new self(
            $id,
            $origem,
            $destino,
            $especie,
            $quantidade,
            $finalidade,
            $emitidaEm,
            $validaAte,
            $chaveIdempotencia,
            $status,
            $canceladaEm,
        );
    }

    public function cancelar(DateTimeImmutable $agora): void
    {
        if ($this->status === StatusGta::Cancelada) {
            throw TransicaoInvalida::jaCancelada($this->id->toString());
        }

        if ($this->vencida($agora)) {
            throw TransicaoInvalida::vencida($this->id->toString());
        }

        $this->status      = StatusGta::Cancelada;
        $this->canceladaEm = $agora;
    }

    public function vencida(DateTimeImmutable $agora): bool
    {
        return $agora > $this->validaAte;
    }
}
