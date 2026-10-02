<?php declare(strict_types=1);

namespace Gta\Domain\Exception;

use RuntimeException;

/**
 * Base de todas as exceções de domínio. A camada HTTP traduz cada subclasse
 * em uma resposta Problem Details (RFC 9457) com o status adequado.
 */
abstract class GtaException extends RuntimeException {}
