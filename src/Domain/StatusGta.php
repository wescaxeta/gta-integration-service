<?php declare(strict_types=1);

namespace Gta\Domain;

enum StatusGta: string
{
    case Emitida   = 'emitida';
    case Cancelada = 'cancelada';
}
