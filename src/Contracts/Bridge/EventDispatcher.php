<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts\Bridge;

interface EventDispatcher
{
    public function dispatch(object $event): void;
}
