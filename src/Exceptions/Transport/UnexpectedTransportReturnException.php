<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Transport;

class UnexpectedTransportReturnException extends TransportException
{
    public static function fromListener(string $transportName, string $listenerType): self
    {
        return new self(
            "Transport '{$transportName}' listener unexpectedly returned in {$listenerType}. "
            . 'Transport listeners should never return as they run in an infinite loop. '
            . 'This indicates a bug in the transport implementation.',
        );
    }
}
