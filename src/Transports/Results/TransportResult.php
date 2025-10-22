<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Results;

abstract class TransportResult
{
    abstract public function isSuccess(): bool;

    abstract public function isSync(): bool;

    /** @return array<string, mixed>|null */
    abstract public function getData(): ?array;

    abstract public function getError(): ?string;
}
