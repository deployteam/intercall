<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Configuration;

use DeployTeam\Intercall\Exceptions\Configuration\SystemNotConfiguredException;

class SystemRegistry
{
    /** @var array<string, RemoteSystemConfig> */
    private array $systems = [];

    private ?LocalSystemConfig $localSystem = null;

    public function registerRemoteSystemConfig(RemoteSystemConfig $config): void
    {
        $this->systems[$config->name] = $config;
    }

    public function registerLocalSystemConfig(LocalSystemConfig $config): void
    {
        $this->localSystem = $config;
    }

    /**
     * @throws SystemNotConfiguredException
     */
    public function getRemoteSystemConfig(string $name): RemoteSystemConfig
    {
        if (!array_key_exists($name, $this->systems)) {
            throw SystemNotConfiguredException::remoteSystem($name);
        }

        return $this->systems[$name];
    }

    /**
     * @throws SystemNotConfiguredException
     */
    public function getLocalSystemConfig(): LocalSystemConfig
    {
        if ($this->localSystem === null) {
            throw SystemNotConfiguredException::currentSystem();
        }

        return $this->localSystem;
    }
}
