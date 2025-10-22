<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Results;

final class FailedTransportResult extends TransportResult
{
    public function __construct(private readonly string $error) {}

    public function isSuccess(): bool
    {
        return false;
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
        return $this->error;
    }
}
