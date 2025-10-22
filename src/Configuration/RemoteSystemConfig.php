<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Configuration;

use DeployTeam\Intercall\Transports\TransportChain;

class RemoteSystemConfig
{
    public function __construct(
        public readonly string $name,
        public readonly string $token,
        public readonly TransportChain $transports,
    ) {}
}
