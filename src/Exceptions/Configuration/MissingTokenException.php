<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Configuration;

class MissingTokenException extends ConfigurationException
{
    public static function forSystem(string $systemName): self
    {
        return new self("No token configured for system '{$systemName}'. Call ->withToken('your-token') when registering the system.");
    }

    public static function forInboundSystem(string $sourceSystem): self
    {
        return new self("No token configured to accept requests from system '{$sourceSystem}'.");
    }
}
