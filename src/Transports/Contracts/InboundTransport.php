<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Contracts;

interface InboundTransport extends Transport
{
    public function getId(): string;

    /**
     * @param callable(array<string, mixed>): void $callback
     * @param array<string, mixed> $options
     */
    public function listen(string $channel, callable $callback, array $options = []): void;

    public function stop(): void;
}
