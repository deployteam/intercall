<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\IntercallEvent;

class RequestBuilder
{
    public function __construct(protected IntercallHub $hub, protected string $targetSystem) {}

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function send(IntercallEvent $event): mixed
    {
        return $this->hub->dispatch($this->targetSystem, $event);
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function sendAsync(IntercallEvent $event): string
    {
        return $this->hub->dispatchAsync($this->targetSystem, $event);
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function sendForget(IntercallEvent $event): string
    {
        return $this->hub->dispatchForget($this->targetSystem, $event);
    }
}
