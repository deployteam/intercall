<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\IntercallEvent;
use DeployTeam\Intercall\Enums\RequestType;
use Exception;

class IntercallHub
{
    /** @param array<string, mixed> $config */
    public function __construct(
        protected RequestDispatcher $dispatcher,
        protected AsyncRequestManager $asyncManager,
        protected array $config,
    ) {}

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatch(string $targetSystem, IntercallEvent $event): mixed
    {
        return $this->dispatcher->dispatch($targetSystem, $event, RequestType::SYNC);
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatchAsync(string $targetSystem, IntercallEvent $event): string
    {
        return $this->dispatcher->dispatch($targetSystem, $event, RequestType::ASYNC);
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatchForget(string $targetSystem, IntercallEvent $event): string
    {
        return $this->dispatcher->dispatch($targetSystem, $event, RequestType::FIRE_AND_FORGET);
    }

    /**
     * @param array<int, string> $targets
     * @param IntercallEvent<array<string, mixed>> $event
     * @return array<string, mixed>
     */
    public function dispatchToMany(array $targets, IntercallEvent $event): array
    {
        $results = [];

        foreach ($targets as $target) {
            try {
                $results[$target] = $this->dispatch($target, $event);
            } catch (Exception $e) {
                $results[$target] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * @param IntercallEvent<array<string, mixed>> $event
     * @return array<string, mixed>
     */
    public function broadcast(IntercallEvent $event): array
    {
        $systems = array_keys($this->config['systems'] ?? []);
        $currentSystem = $this->config['system_id'];

        $targets = array_filter($systems, fn(int|string $system): bool => $system !== $currentSystem);

        /** @var array<int, string> $targetList */
        $targetList = array_values($targets);
        return $this->dispatchToMany($targetList, $event);
    }

    /** @return array<string, mixed>|null */
    public function wait(string $requestId, int $timeout = 30): ?array
    {
        return $this->asyncManager->waitForCompletion($requestId, $timeout);
    }

    /** @return array<string, mixed>|null */
    public function status(string $requestId): ?array
    {
        return $this->asyncManager->getStatus($requestId);
    }

    public function to(string $targetSystem): RequestBuilder
    {
        return new RequestBuilder($this, $targetSystem);
    }
}
