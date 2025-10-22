<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Transport;

use RuntimeException;

class UnsupportedTransportException extends RuntimeException
{
    public static function forType(string $type): self
    {
        return new self("Unsupported transport type: {$type}");
    }
}
