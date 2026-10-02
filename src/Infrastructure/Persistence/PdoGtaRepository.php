<?php declare(strict_types=1);

namespace Gta\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Gta\Domain\ChaveIdempotencia;
use Gta\Domain\CodigoPropriedade;
use Gta\Domain\Especie;
use Gta\Domain\Exception\ChaveIdempotenciaJaUtilizada;
use Gta\Domain\Finalidade;
use Gta\Domain\Gta;
use Gta\Domain\GtaRepository;
use Gta\Domain\StatusGta;
use PDO;
use PDOException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use UnexpectedValueException;

final readonly class PdoGtaRepository implements GtaRepository
{
    private const string VIOLACAO_UNICIDADE      = '23505';
    private const string CONSTRAINT_IDEMPOTENCIA = 'uq_gta_chave_idempotencia';
    private const string FORMATO_DATA            = 'Y-m-d H:i:s.uP';

    public function __construct(
        private PDO $pdo,
    ) {}

    public function adicionar(Gta $gta): void
    {
        $sql = <<<'SQL'
            INSERT INTO gta (
                id, origem, destino, especie, quantidade, finalidade, status,
                emitida_em, valida_ate, cancelada_em, chave_idempotencia, hash_requisicao
            ) VALUES (
                :id, :origem, :destino, :especie, :quantidade, :finalidade, :status,
                :emitida_em, :valida_ate, :cancelada_em, :chave_idempotencia, :hash_requisicao
            )
            SQL;

        try {
            $this->pdo->prepare($sql)->execute([
                'id'                 => $gta->id->toString(),
                'origem'             => $gta->origem->valor,
                'destino'            => $gta->destino->valor,
                'especie'            => $gta->especie->value,
                'quantidade'         => $gta->quantidade,
                'finalidade'         => $gta->finalidade->value,
                'status'             => $gta->status->value,
                'emitida_em'         => $gta->emitidaEm->format(self::FORMATO_DATA),
                'valida_ate'         => $gta->validaAte->format(self::FORMATO_DATA),
                'cancelada_em'       => $gta->canceladaEm?->format(self::FORMATO_DATA),
                'chave_idempotencia' => $gta->chaveIdempotencia->chave,
                'hash_requisicao'    => $gta->chaveIdempotencia->hashRequisicao,
            ]);
        } catch (PDOException $erro) {
            if ($this->violouIdempotencia($erro)) {
                throw ChaveIdempotenciaJaUtilizada::chave($gta->chaveIdempotencia->chave);
            }

            throw $erro;
        }
    }

    public function atualizar(Gta $gta): void
    {
        $this->pdo
            ->prepare('UPDATE gta SET status = :status, cancelada_em = :cancelada_em WHERE id = :id')
            ->execute([
                'id'           => $gta->id->toString(),
                'status'       => $gta->status->value,
                'cancelada_em' => $gta->canceladaEm?->format(self::FORMATO_DATA),
            ]);
    }

    public function buscar(UuidInterface $id): ?Gta
    {
        return $this->buscarUma('SELECT * FROM gta WHERE id = :valor', $id->toString());
    }

    public function buscarPorChaveIdempotencia(string $chave): ?Gta
    {
        return $this->buscarUma('SELECT * FROM gta WHERE chave_idempotencia = :valor', $chave);
    }

    private function buscarUma(string $sql, string $valor): ?Gta
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute(['valor' => $valor]);

        /** @var array<string, mixed>|false $linha */
        $linha = $consulta->fetch(PDO::FETCH_ASSOC);

        return $linha === false ? null : $this->hidratar($linha);
    }

    /**
     * @param array<string, mixed> $linha
     */
    private function hidratar(array $linha): Gta
    {
        $canceladaEm = $linha['cancelada_em'] ?? null;

        return Gta::reconstituir(
            id: Uuid::fromString($this->texto($linha, 'id')),
            origem: new CodigoPropriedade($this->texto($linha, 'origem')),
            destino: new CodigoPropriedade($this->texto($linha, 'destino')),
            especie: Especie::from($this->texto($linha, 'especie')),
            quantidade: (int) $this->texto($linha, 'quantidade'),
            finalidade: Finalidade::from($this->texto($linha, 'finalidade')),
            emitidaEm: $this->data($this->texto($linha, 'emitida_em')),
            validaAte: $this->data($this->texto($linha, 'valida_ate')),
            chaveIdempotencia: ChaveIdempotencia::reconstituir(
                $this->texto($linha, 'chave_idempotencia'),
                $this->texto($linha, 'hash_requisicao'),
            ),
            status: StatusGta::from($this->texto($linha, 'status')),
            canceladaEm: $canceladaEm === null ? null : $this->data($this->texto($linha, 'cancelada_em')),
        );
    }

    /**
     * @param array<string, mixed> $linha
     */
    private function texto(array $linha, string $coluna): string
    {
        $valor = $linha[$coluna] ?? null;

        if (is_int($valor)) {
            return (string) $valor;
        }

        return is_string($valor)
            ? $valor
            : throw new UnexpectedValueException(sprintf('Coluna "%s" ausente ou com tipo inesperado.', $coluna));
    }

    private function data(string $valor): DateTimeImmutable
    {
        return new DateTimeImmutable($valor)->setTimezone(new DateTimeZone('UTC'));
    }

    private function violouIdempotencia(PDOException $erro): bool
    {
        $sqlState = $erro->errorInfo[0] ?? null;

        return $sqlState === self::VIOLACAO_UNICIDADE
            && str_contains($erro->getMessage(), self::CONSTRAINT_IDEMPOTENCIA);
    }
}
