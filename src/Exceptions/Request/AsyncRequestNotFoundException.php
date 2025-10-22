<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Request;

class AsyncRequestNotFoundException extends RequestException
{
    public static function forRequestId(string $requestId): self
    {
        return new self("Async request '{$requestId}' not found. It may have expired or never existed.");
    }
}
