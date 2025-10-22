<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Results;

final class AsyncTransportResult extends TransportResult
{
    public function isSuccess(): bool
    {
        return true;
    }

    public function isSync(): bool
    {
        return false;
    }

    public function getData(): ?array
    {
        return null;
    }

    public function getError(): ?string
    {
        return null;
    }
}
