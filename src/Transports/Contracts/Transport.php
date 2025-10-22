<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Contracts;

interface Transport
{
    public function isAvailable(): bool;
}
