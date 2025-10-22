<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Transport;

class NoTransportAvailableException extends TransportException
{
    public static function forListening(): self
    {
        return new self(
            'No listening transport available. Ensure Redis or HTTP transport is configured and accessible.',
        );
    }

    public static function forDispatching(string $targetSystem): self
    {
        return new self(
            "No available transport to reach system '{$targetSystem}'. "
            . 'Check transport configuration and connectivity.',
        );
    }

    public static function notRegistered(string $transportName): self
    {
        return new self(
            "Transport '{$transportName}' is not registered. Register it using TransportManager::register().",
        );
    }

    public static function notRegisteredWithId(string $transportId): self
    {
        return new self(
            "Transport with ID '{$transportId}' is not registered.",
        );
    }
}
