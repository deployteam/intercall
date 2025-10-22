<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Request;

class RequestTimeoutException extends RequestException
{
    public static function afterSeconds(int $seconds): self
    {
        return new self("Request timeout after {$seconds} seconds. The remote system did not respond in time.");
    }
}
