<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Contracts\Bridge\Redis;
use Throwable;

class IdempotencyManager
{
    private const KEY_PREFIX = 'intercall:idempotency';

    /** @param array<string, mixed> $config */
    public function __construct(
        protected Redis $redis,
        protected MessageSerializer $serializer,
        protected Logger $logger,
        protected array $config,
    ) {}

    /** @return array{result: mixed, error: string|null}|null */
    public function getCachedResponse(string $requestId): ?array
    {
        try {
            $key = $this->getKey($requestId);
            $cached = $this->redis->get($key);

            if ($cached === null) {
                return null;
            }

            $data = $this->serializer->deserialize($cached);

            $this->logger->debug('[Intercall Idempotency] Cache hit', [
                'request_id' => $requestId,
            ]);

            return $data;
        } catch (Throwable $e) {
            $this->logger->error('[Intercall Idempotency] Failed to retrieve cached response', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function cacheResponse(string $requestId, mixed $result, ?string $error = null): void
    {
        try {
            $key = $this->getKey($requestId);
            $ttl = $this->getTtl();

            $data = [
                'result' => $result,
                'error' => $error,
                'cached_at' => time(),
            ];

            $serialized = $this->serializer->serialize($data);
            $this->redis->setex($key, $ttl, $serialized);

            $this->logger->debug('[Intercall Idempotency] Response cached', [
                'request_id' => $requestId,
                'ttl' => $ttl,
                'has_error' => $error !== null,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('[Intercall Idempotency] Failed to cache response', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function markAsProcessing(string $requestId): bool
    {
        try {
            $lockKey = $this->getLockKey($requestId);
            $lockTtl = 300;

            $result = $this->redis->setex($lockKey, $lockTtl, '1');

            if (!$result) {
                $this->logger->warning('[Intercall Idempotency] Request already being processed', [
                    'request_id' => $requestId,
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            $this->logger->error('[Intercall Idempotency] Failed to set processing lock', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return true;
        }
    }

    protected function getKey(string $requestId): string
    {
        $prefix = $this->getPrefix();
        return "{$prefix}:result:{$requestId}";
    }

    protected function getLockKey(string $requestId): string
    {
        $prefix = $this->getPrefix();
        return "{$prefix}:lock:{$requestId}";
    }

    protected function getPrefix(): string
    {
        $idempotencyConfig = $this->config['idempotency'] ?? [];
        assert(is_array($idempotencyConfig));
        $prefix = $idempotencyConfig['prefix'] ?? self::KEY_PREFIX;
        assert(is_string($prefix));
        return $prefix;
    }

    protected function getTtl(): int
    {
        $idempotencyConfig = $this->config['idempotency'] ?? [];
        assert(is_array($idempotencyConfig));
        $ttl = $idempotencyConfig['ttl'] ?? 3600;
        assert(is_int($ttl));
        return $ttl;
    }
}
