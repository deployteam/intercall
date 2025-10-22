<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Events;

class HandlerNotFoundException extends EventException
{
    public static function forEvent(string $eventName): self
    {
        return new self(
            "No handler registered for event '{$eventName}'. "
            . 'Register a handler using Intercall::registerHandler().',
        );
    }
}
