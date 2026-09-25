<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

interface HasResponseTimeout
{
    public function getResponseTimeout(): int;
}
