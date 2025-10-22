<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

interface EventHandler
{
    /**
     * @return string Event name (e.g., 'user.created') or class-string<IntercallEvent>
     */
    public function handles(): string;

    /**
     * @param TEvent $event The event to handle (will be the type specified in @implements)
     * @param array<string, mixed> $context Additional context (source_system, request_id, etc.)
     * @return mixed The result to send back (for sync/async requests)
     */
    public function handle(IntercallEvent $event, array $context = []): mixed;
}
