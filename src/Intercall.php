<?php

declare(strict_types=1);

namespace DeployTeam\Intercall;

use DeployTeam\Intercall\Configuration\LocalSystemConfig;
use DeployTeam\Intercall\Configuration\RemoteSystemConfig;
use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Transports\TransportChain;

class Intercall
{
    private static SystemRegistry $registry;

    public static function init(SystemRegistry $registry): void
    {
        self::$registry = $registry;
    }

    public static function registerRemoteSystem(
        string $name,
        string $token,
        TransportChain $transports,
    ): RemoteSystemConfig {
        $config = new RemoteSystemConfig($name, $token, $transports);

        self::$registry->registerRemoteSystemConfig($config);

        return $config;
    }

    public static function registerLocalSystem(
        string $name,
        TransportChain $transports,
    ): LocalSystemConfig {
        $config = new LocalSystemConfig($name, $transports);

        self::$registry->registerLocalSystemConfig($config);

        return $config;
    }
}
