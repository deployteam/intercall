<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Events;

class InvalidEventException extends EventException
{
    public static function classNotFound(string $eventClass): self
    {
        return new self("Event class '{$eventClass}' does not exist.");
    }

    public static function doesNotImplementInterface(string $eventClass): self
    {
        return new self("Event '{$eventClass}' must implement IntercallEvent interface.");
    }

    public static function missingRequiredParameter(string $className, string $parameterName): self
    {
        return new self("Missing required parameter '{$parameterName}' for {$className}");
    }
}
