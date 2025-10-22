<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Results;

final class SyncTransportResult extends TransportResult
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public function isSuccess(): bool
    {
        return true;
    }

    public function isSync(): bool
    {
        return true;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getError(): ?string
    {
        return null;
    }
}
