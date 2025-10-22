<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Transport;

class UnsupportedOperationException extends TransportException
{
    public static function listeningNotSupported(string $transportName): self
    {
        return new self(
            "Transport '{$transportName}' does not support listening. "
            . 'Use a queue-based transport like Redis instead.',
        );
    }
}
