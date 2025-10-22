<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\Bridge\Redis;
use DeployTeam\Intercall\Enums\AsyncStatus;
use DeployTeam\Intercall\Exceptions\Request\AsyncRequestNotFoundException;
use DeployTeam\Intercall\Exceptions\Serialization\SerializationException;

use function Safe\json_decode;
use function Safe\json_encode;

class AsyncRequestManager
{
    public function __construct(
        protected Redis $redis,
        protected int $statusTtl = 3600,
        protected string $redisPrefix = 'intercall',
    ) {}

    public function setStatus(string $requestId, AsyncStatus $status, mixed $data = null): void
    {
        $key = $this->getStatusKey($requestId);

        $statusData = [
            'status' => $status->value,
            'data' => $data,
            'updated_at' => time(),
        ];

        $this->redis->setex(
            $key,
            $this->statusTtl,
            json_encode($statusData, JSON_THROW_ON_ERROR),
        );

        $this->redis->publish(
            "{$this->redisPrefix}:async:updates",
            json_encode([
                'request_id' => $requestId,
                'status' => $status->value,
            ], JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    public function getStatus(string $requestId): array
    {
        $key = $this->getStatusKey($requestId);
        $data = $this->redis->get($key);

        if ($data === null) {
            throw AsyncRequestNotFoundException::forRequestId($requestId);
        }

        $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw SerializationException::invalidData('Async request status data is not valid.');
        }

        return $decoded;
    }

    /** @return array<string, mixed> */
    public function waitForCompletion(string $requestId, int $timeout = 30): array
    {
        $startTime = time();

        while (true) {
            try {
                $status = $this->getStatus($requestId);
            } catch (AsyncRequestNotFoundException $e) {
                throw $e;
            }

            $currentStatus = AsyncStatus::from($status['status']);

            if ($currentStatus === AsyncStatus::COMPLETED || $currentStatus === AsyncStatus::FAILED) {
                return $status;
            }

            if (time() - $startTime >= $timeout) {
                $this->setStatus($requestId, AsyncStatus::TIMEOUT);
                return $this->getStatus($requestId);
            }

            usleep(100000);
        }
    }

    public function cleanup(): int
    {
        return 0;
    }

    protected function getStatusKey(string $requestId): string
    {
        return "{$this->redisPrefix}:async:status:{$requestId}";
    }
}
