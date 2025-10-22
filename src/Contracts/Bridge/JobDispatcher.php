<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts\Bridge;

interface JobDispatcher
{
    /**
     * @param callable(): void $job
     */
    public function dispatch(callable $job): void;
}
