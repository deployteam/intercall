<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts\Bridge;

interface ConsoleOutput
{
    public function info(string $message): void;

    public function error(string $message): void;

    public function warning(string $message): void;

    public function newLine(int $count = 1): void;

    public function option(string $key, mixed $default = null): mixed;
}
