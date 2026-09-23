<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

interface InboundMiddleware
{
    /**
     * Wrap handler execution. Extract any state from envelope before calling $next(),
     * clean up (e.g., detach scope) in finally.
     *
     * @param array<string, mixed> $envelope
     * @param callable(): mixed $next
     */
    public function handle(array $envelope, callable $next): mixed;
}
