<?php declare(strict_types=1);

namespace Gta\Domain;

enum Finalidade: string
{
    case Abate      = 'abate';
    case Engorda    = 'engorda';
    case Reproducao = 'reproducao';
    case Exposicao  = 'exposicao';
}
