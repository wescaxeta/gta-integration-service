<?php declare(strict_types=1);

namespace Gta\Domain;

enum Especie: string
{
    case Bovino   = 'bovino';
    case Bubalino = 'bubalino';
    case Suino    = 'suino';
    case Equino   = 'equino';
    case Ovino    = 'ovino';
    case Caprino  = 'caprino';
}
