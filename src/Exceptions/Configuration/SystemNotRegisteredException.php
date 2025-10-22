<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Configuration;

class SystemNotRegisteredException extends ConfigurationException
{
    public static function forSystem(string $systemName): self
    {
        return new self("Target system '{$systemName}' is not registered. Use Intercall::system('{$systemName}') to register it.");
    }
}
