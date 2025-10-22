<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Contracts;

interface TransportHasPrefix
{
    public function getPrefix(): string;
}
