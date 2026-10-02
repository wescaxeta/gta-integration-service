<?php declare(strict_types=1);

namespace Gta\Domain;

/**
 * Propriedade rural como o domínio a enxerga, já traduzida da resposta do
 * cadastro agropecuário externo.
 */
final readonly class Propriedade
{
    public function __construct(
        public CodigoPropriedade $codigo,
        public string $nome,
        public string $municipio,
        public SituacaoPropriedade $situacao,
    ) {}

    public function podeEnviarAnimais(): bool
    {
        return $this->situacao === SituacaoPropriedade::Ativa;
    }

    /** Propriedade bloqueada ainda pode receber animais; inativa, não. */
    public function podeReceberAnimais(): bool
    {
        return $this->situacao !== SituacaoPropriedade::Inativa;
    }
}
