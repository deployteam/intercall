<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

abstract class BaseCallbackHandler implements CallbackHandler
{
    /**
     * @return class-string<IntercallEvent<array<string, mixed>>>
     */
    abstract public function handles(): string;

    /**
     * @param IntercallEvent<array<string, mixed>> $originalEvent
     * @param string $requestId
     * @param mixed $result
     * @param bool $success
     */
    public function __construct(
        protected readonly IntercallEvent $originalEvent,
        protected readonly string $requestId,
        protected readonly mixed $result,
        protected readonly bool $success,
    ) {}

    /**
     * @return IntercallEvent<array<string, mixed>>
     */
    protected function getEvent(): IntercallEvent
    {
        return $this->originalEvent;
    }

    protected function getRequestId(): string
    {
        return $this->requestId;
    }

    protected function getResult(): mixed
    {
        return $this->result;
    }

    protected function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOriginalPayload(): array
    {
        return $this->originalEvent->getPayload();
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        logger()->info("[Intercall Callback] {$message}", array_merge([
            'request_id' => $this->requestId,
        ], $context));
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    protected function logError(string $message, array $context = []): void
    {
        logger()->error("[Intercall Callback] {$message}", array_merge([
            'request_id' => $this->requestId,
        ], $context));
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        logger()->warning("[Intercall Callback] {$message}", array_merge([
            'request_id' => $this->requestId,
        ], $context));
    }
}
