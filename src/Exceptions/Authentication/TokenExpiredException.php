<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Authentication;

class TokenExpiredException extends AuthenticationException
{
    public static function expired(): self
    {
        return new self('Token has expired. Generate a new token.');
    }

    public static function futureTimestamp(): self
    {
        return new self('Token timestamp is in the future. Check system clocks are synchronized.');
    }
}
