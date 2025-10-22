<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Events;

class AsyncResponseReceived
{
    public function __construct(
        public string $requestId,
        public string $originalEventName,
        public mixed $response,
        public bool $success,
    ) {}
}
