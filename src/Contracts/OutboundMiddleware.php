<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

interface OutboundMiddleware
{
    /**
     * Wrap outbound dispatch. Mutate envelope before $next() as needed;
     * clean up (e.g., end span, detach scope) in finally.
     *
     * @param array<string, mixed> $envelope
     * @param callable(): mixed $next
     */
    public function handle(array &$envelope, callable $next): mixed;
}
