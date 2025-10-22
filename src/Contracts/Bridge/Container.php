<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts\Bridge;

interface Container
{
    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function make(string $abstract): object;
}
