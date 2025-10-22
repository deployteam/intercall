<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Events;

use DeployTeam\Intercall\Contracts\IntercallEvent;

class RequestReceived
{
    /**
     * @param IntercallEvent<array<string, mixed>> $event
     */
    public function __construct(
        public string $requestId,
        public string $sourceSystem,
        public string $eventName,
        public IntercallEvent $event,
    ) {}
}
