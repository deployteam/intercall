<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts\Bridge;

interface HttpResponse
{
    public function getStatusCode(): int;

    public function getBody(): string;

    /** @return array<string, mixed> */
    public function toArray(): array;

    public function isSuccessful(): bool;
}
