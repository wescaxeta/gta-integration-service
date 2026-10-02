<?php declare(strict_types=1);

namespace Gta\Domain;

enum SituacaoPropriedade: string
{
    case Ativa     = 'ATIVA';
    case Bloqueada = 'BLOQUEADA';
    case Inativa   = 'INATIVA';
}
