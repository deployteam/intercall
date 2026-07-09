<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Contracts\Bridge\Redis;
use DeployTeam\Intercall\Exceptions\RateLimit\RateLimitException;
use RedisException;

class RateLimiter
{
    public function __construct(
        protected Redis $redis,
        protected int $maxRequests = 1000,
        protected int $burstLimit = 50,
        protected string $redisPrefix = 'intercall',
        protected ?Logger $logger = null,
    ) {}

    public function attempt(string $identifier): bool
    {
        try {
            if (!$this->checkBurstLimit($identifier)) {
                throw RateLimitException::burstLimitExceeded($identifier, $this->burstLimit);
            }

            if (!$this->checkRegularLimit($identifier)) {
                throw RateLimitException::maxRequestsExceeded($identifier, $this->maxRequests);
            }
        } catch (RedisException $e) {
            $this->logger?->warning('[Intercall RateLimiter] Redis unavailable, failing open', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    public function remaining(string $identifier): int
    {
        $key = $this->getRegularKey($identifier);
        $attempts = (int) ($this->redis->get($key) ?? 0);

        return max(0, $this->maxRequests - $attempts);
    }

    public function resetAt(string $identifier): int
    {
        return $this->getRegularResetAt($identifier);
    }

    protected function checkBurstLimit(string $identifier): bool
    {
        $key = $this->getBurstKey($identifier);

        $attempts = $this->redis->incr($key);

        if ($attempts === 1 || $this->redis->ttl($key) === -1) {
            $this->redis->expire($key, 5);
        }

        return $attempts <= $this->burstLimit;
    }

    protected function checkRegularLimit(string $identifier): bool
    {
        $key = $this->getRegularKey($identifier);

        $attempts = $this->redis->incr($key);

        if ($attempts === 1 || $this->redis->ttl($key) === -1) {
            $this->redis->expire($key, 60);
        }

        return $attempts <= $this->maxRequests;
    }

    protected function getRegularKey(string $identifier): string
    {
        return "{$this->redisPrefix}:rate_limit:{$identifier}";
    }

    protected function getBurstKey(string $identifier): string
    {
        return "{$this->redisPrefix}:rate_limit:burst:{$identifier}";
    }

    protected function getRegularResetAt(string $identifier): int
    {
        $key = $this->getRegularKey($identifier);
        $ttl = $this->redis->ttl($key);

        return $ttl > 0 ? time() + $ttl : time();
    }

    protected function getBurstResetAt(string $identifier): int
    {
        $key = $this->getBurstKey($identifier);
        $ttl = $this->redis->ttl($key);

        return $ttl > 0 ? time() + $ttl : time();
    }
}
