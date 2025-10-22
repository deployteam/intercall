<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Configuration;

class SystemNotConfiguredException extends ConfigurationException
{
    public static function currentSystem(): self
    {
        return new self('Current system is not configured. Call Intercall::defineCurrentSystem() first.');
    }

    public static function remoteSystem(string $destination): self
    {
        return new self("System {$destination} is not configured. Call Intercall::defineSystem first.");
    }
}
