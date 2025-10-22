<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\Bridge\Container;
use DeployTeam\Intercall\Contracts\CallbackHandler;
use DeployTeam\Intercall\Contracts\EventHandler;
use DeployTeam\Intercall\Contracts\IntercallEvent;
use DeployTeam\Intercall\Exceptions\Events\HandlerNotFoundException;
use DeployTeam\Intercall\Exceptions\Events\InvalidEventException;
use DeployTeam\Intercall\Exceptions\Events\InvalidHandlerException;

class EventRegistry
{
    /**
     * @var array<string, class-string<EventHandler<IntercallEvent<array<string, mixed>>>>>
     */
    protected array $handlers = [];

    /**
     * @var array<string, class-string<CallbackHandler>>
     */
    protected array $callbackHandlers = [];

    /**
     * @var array<string, class-string<IntercallEvent<array<string, mixed>>>>
     */
    protected array $eventToClass = [];

    /**
     * @var array<string, class-string<IntercallEvent<array<string, mixed>>>>
     */
    protected array $asyncMappings = [];

    public function __construct(protected Container $container) {}

    /** @phpstan-param class-string<EventHandler> $handlerClass */
    public function register(string $handlerClass): void
    {
        if (!class_exists($handlerClass)) {
            throw InvalidHandlerException::classNotFound($handlerClass);
        }

        $handler = $this->container->make($handlerClass);

        if (!$handler instanceof EventHandler) {
            throw InvalidHandlerException::doesNotImplementInterface($handlerClass);
        }

        $handleIdentifier = $handler->handles();
        $eventName = $this->resolveEventName($handleIdentifier);

        $this->handlers[$eventName] = $handlerClass;
    }

    /** @phpstan-param class-string<IntercallEvent> $eventClass */
    public function registerEventClass(string $eventClass): void
    {
        if (!class_exists($eventClass)) {
            throw InvalidEventException::classNotFound($eventClass);
        }

        $event = new $eventClass([]);

        if (!$event instanceof IntercallEvent) {
            throw InvalidEventException::doesNotImplementInterface($eventClass);
        }

        $eventName = $event->getEventName();
        $this->eventToClass[$eventName] = $eventClass;
    }

    /** @phpstan-param class-string<CallbackHandler> $callbackHandlerClass */
    public function registerCallbackHandler(string $callbackHandlerClass): void
    {
        if (!class_exists($callbackHandlerClass)) {
            throw InvalidHandlerException::classNotFound($callbackHandlerClass);
        }

        $handleIdentifier = $this->extractEventClassFromCallbackHandler($callbackHandlerClass);
        $eventName = $this->resolveEventName($handleIdentifier);

        $this->callbackHandlers[$eventName] = $callbackHandlerClass;
    }

    protected function extractEventClassFromCallbackHandler(string $handlerClass): string
    {
        $reflection = new \ReflectionClass($handlerClass);
        $filename = $reflection->getFileName();

        if ($filename === false) {
            throw new \RuntimeException("Could not find file for {$handlerClass}");
        }

        $contents = file_get_contents($filename);
        if ($contents === false) {
            throw new \RuntimeException("Could not read file for {$handlerClass}");
        }

        if (preg_match('/function\s+handles\s*\(\s*\)\s*:\s*string\s*\{[^}]*return\s+([A-Za-z0-9_\\\\]+)::class/', $contents, $matches)) {
            $eventClassShortName = trim($matches[1]);

            if (str_contains($eventClassShortName, '\\')) {
                return ltrim($eventClassShortName, '\\');
            }

            if (preg_match('/use\s+([^;]+\\\\' . preg_quote($eventClassShortName, '/') . ');/', $contents, $useMatches)) {
                return trim($useMatches[1]);
            }

            $namespace = $reflection->getNamespaceName();
            return $namespace . '\\' . $eventClassShortName;
        }

        throw new \RuntimeException("Could not extract event class from {$handlerClass}::handles()");
    }

    /** @phpstan-param class-string<IntercallEvent> $responseEventClass */
    public function mapAsyncResponse(string $requestEventName, string $responseEventClass): void
    {
        $this->asyncMappings[$requestEventName] = $responseEventClass;
    }

    /** @phpstan-return EventHandler */
    public function getHandler(string $eventName): EventHandler
    {
        if (!isset($this->handlers[$eventName])) {
            throw HandlerNotFoundException::forEvent($eventName);
        }

        $handler = $this->container->make($this->handlers[$eventName]);
        assert($handler instanceof EventHandler);
        return $handler;
    }

    /** @phpstan-return class-string<IntercallEvent>|null */
    public function getEventClass(string $eventName): ?string
    {
        return $this->eventToClass[$eventName] ?? null;
    }

    /** @phpstan-return class-string<CallbackHandler>|null */
    public function getCallbackHandler(string $eventName): ?string
    {
        return $this->callbackHandlers[$eventName] ?? null;
    }

    /** @phpstan-return class-string<IntercallEvent>|null */
    public function getAsyncMapping(string $requestEventName): ?string
    {
        return $this->asyncMappings[$requestEventName] ?? null;
    }

    public function hasHandler(string $eventName): bool
    {
        return isset($this->handlers[$eventName]);
    }

    /** @return array<int, string> */
    public function getRegisteredEvents(): array
    {
        return array_keys($this->handlers);
    }

    protected function resolveEventName(string $identifier): string
    {
        if (class_exists($identifier)) {
            $event = new $identifier([]);

            if ($event instanceof IntercallEvent) {
                $eventName = $event->getEventName();
                $this->eventToClass[$eventName] = $identifier;
                return $eventName;
            }
        }

        return $identifier;
    }
}
