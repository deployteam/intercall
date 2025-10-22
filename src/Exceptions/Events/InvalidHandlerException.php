<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Events;

class InvalidHandlerException extends EventException
{
    public static function classNotFound(string $handlerClass): self
    {
        return new self("Handler class '{$handlerClass}' does not exist.");
    }

    public static function doesNotImplementInterface(string $handlerClass): self
    {
        return new self("Handler '{$handlerClass}' must implement EventHandler interface.");
    }
}
