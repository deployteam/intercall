<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Transport;

class RedisConnectionException extends TransportException
{
    public static function failedToConnect(string $host, int $port): self
    {
        return new self("Failed to connect to Redis at {$host}:{$port}. Check that Redis is running and accessible.");
    }

    public static function extensionNotInstalled(): self
    {
        return new self('phpredis extension is not installed. Install it or use predis library.');
    }

    public static function phpredisNotInstalled(): self
    {
        return new self('phpredis extension is not installed.');
    }

    public static function predisNotInstalled(): self
    {
        return new self('predis library is not installed. Run: composer require predis/predis');
    }

    public static function invalidDriver(string $driver): self
    {
        return new self("Invalid Redis driver '{$driver}'. Must be 'phpredis' or 'predis'.");
    }

    public static function noDriverAvailable(): self
    {
        return new self('No Redis driver available. Install phpredis extension or run: composer require predis/predis');
    }
}
