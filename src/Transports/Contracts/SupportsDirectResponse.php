<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Contracts;

interface SupportsDirectResponse
{
    public function sendToChannel(string $channel, string $serializedData, int $ttl): void;

    /** @return array{0: string, 1: string}|null */
    public function receiveFromChannel(string $channel, int $timeout): ?array;
}
