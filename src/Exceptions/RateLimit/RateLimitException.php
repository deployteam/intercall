<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\RateLimit;

use DeployTeam\Intercall\Exceptions\IntercallException;

class RateLimitException extends IntercallException
{
    public static function maxRequestsExceeded(string $system, int $limit): self
    {
        return new self("Rate limit exceeded for system '{$system}'. Maximum {$limit} requests per minute allowed.");
    }

    public static function burstLimitExceeded(string $system, int $limit): self
    {
        return new self(
            "Burst limit exceeded for system '{$system}'. Maximum {$limit} requests per 5 seconds allowed.",
        );
    }
}
