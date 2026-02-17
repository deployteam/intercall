<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

use DeployTeam\Intercall\Services\RequestBuilder;

interface IntercallHubContract
{
    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatch(string $targetSystem, IntercallEvent $event): mixed;

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatchAsync(string $targetSystem, IntercallEvent $event): string;

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatchForget(string $targetSystem, IntercallEvent $event): string;

    /**
     * @param array<int, string> $targets
     * @param IntercallEvent<array<string, mixed>> $event
     * @return array<string, mixed>
     */
    public function dispatchToMany(array $targets, IntercallEvent $event): array;

    /**
     * @param IntercallEvent<array<string, mixed>> $event
     * @return array<string, mixed>
     */
    public function broadcast(IntercallEvent $event): array;

    /** @return array<string, mixed>|null */
    public function wait(string $requestId, int $timeout = 30): ?array;

    /** @return array<string, mixed>|null */
    public function status(string $requestId): ?array;

    public function to(string $targetSystem): RequestBuilder;
}
