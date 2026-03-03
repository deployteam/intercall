<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts\Bridge;

interface Redis
{
    public function lpush(string $key, string $value): int|false;

    /**
     * @param string|array<string> $keys
     * @return array<int, string>|null
     */
    public function brpop(string|array $keys, int $timeout): ?array;

    /**
     * @param string|array<string> $keys
     * @return array<int, string>|null
     */
    public function blpop(string|array $keys, int $timeout): ?array;

    public function setex(string $key, int $ttl, string $value): bool;

    public function get(string $key): ?string;

    public function exists(string $key): bool;

    public function incr(string $key): int;

    public function expire(string $key, int $ttl): bool;

    public function ttl(string $key): int;

    public function publish(string $channel, string $message): int;

    /**
     * @return array<int, string>
     */
    public function keys(string $pattern): array;

    public function del(string $key): int;

    public function disconnect(): void;
}
