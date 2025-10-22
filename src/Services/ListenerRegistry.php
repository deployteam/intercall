<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Contracts\Bridge\Redis;

class ListenerRegistry
{
    protected string $heartbeatKeyPrefix;
    protected bool $useRedis = false;

    public function __construct(
        protected SystemRegistry $systemRegistry,
        protected ?Redis $redis,
        string $redisPrefix = 'intercall',
        protected ?string $storagePath = null,
    ) {
        $prefix = $redisPrefix !== '' ? "{$redisPrefix}:heartbeat:listener" : 'heartbeat:listener';
        $this->heartbeatKeyPrefix = $prefix;

        $this->useRedis = $redis !== null && $this->testRedisConnection();

        if (!$this->useRedis && $this->storagePath !== null) {
            $this->ensureStorageDirectoryExists();
        }
    }

    public function register(string $transportId): void
    {
        if ($this->useRedis) {
            $this->registerRedis($transportId);
        } else {
            $this->registerFilesystem($transportId);
        }
    }

    public function unregister(string $transportId): void
    {
        if ($this->useRedis) {
            $this->unregisterRedis($transportId);
        } else {
            $this->unregisterFilesystem($transportId);
        }
    }

    public function hasActiveListeners(): bool
    {
        $listeners = $this->getActiveListeners();
        return count($listeners) > 0;
    }

    /**
     * @return array<string, int>
     */
    public function getActiveListeners(): array
    {
        if ($this->useRedis) {
            return $this->getActiveListenersRedis();
        }

        return $this->getActiveListenersFilesystem();
    }

    /**
     * @return array<string, mixed>
     */
    public function getHeartbeatData(): array
    {
        $systemName = $this->systemRegistry->getLocalSystemConfig()->name;
        $listeners = $this->getActiveListeners();

        return [
            'status' => 'ok',
            'system' => $systemName,
            'timestamp' => time(),
            'listeners' => $listeners,
            'listener_count' => count($listeners),
            'storage' => $this->useRedis ? 'redis' : 'filesystem',
        ];
    }

    protected function testRedisConnection(): bool
    {
        try {
            $this->redis->exists('intercall:test');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function registerRedis(string $transportId): void
    {
        $key = "{$this->heartbeatKeyPrefix}:{$transportId}";
        $this->redis->setex($key, 30, (string) time());
    }

    protected function unregisterRedis(string $transportId): void
    {
        $key = "{$this->heartbeatKeyPrefix}:{$transportId}";
        $this->redis->del($key);
    }

    /** @return array<string, int> */
    protected function getActiveListenersRedis(): array
    {
        $pattern = "{$this->heartbeatKeyPrefix}:*";
        $keys = $this->redis->keys($pattern);

        $listeners = [];
        foreach ($keys as $key) {
            $parts = explode(':', $key);
            $transportId = end($parts);

            $timestamp = $this->redis->get($key);
            if ($timestamp !== null) {
                $listeners[$transportId] = (int) $timestamp;
            }
        }

        return $listeners;
    }

    protected function ensureStorageDirectoryExists(): void
    {
        if ($this->storagePath === null) {
            return;
        }

        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }
    }

    protected function getHeartbeatFilePath(string $transportId): string
    {
        return $this->storagePath . '/listener-' . $transportId . '.heartbeat';
    }

    protected function registerFilesystem(string $transportId): void
    {
        if ($this->storagePath === null) {
            return;
        }

        $filePath = $this->getHeartbeatFilePath($transportId);
        $timestamp = time();

        $tempFile = $filePath . '.tmp';
        file_put_contents($tempFile, (string) $timestamp);
        @rename($tempFile, $filePath);
    }

    protected function unregisterFilesystem(string $transportId): void
    {
        if ($this->storagePath === null) {
            return;
        }

        $filePath = $this->getHeartbeatFilePath($transportId);
        @unlink($filePath);
    }

    /** @return array<string, int> */
    protected function getActiveListenersFilesystem(): array
    {
        if ($this->storagePath === null || !is_dir($this->storagePath)) {
            return [];
        }

        $listeners = [];
        $pattern = $this->storagePath . '/listener-*.heartbeat';
        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        $now = time();

        foreach ($files as $file) {
            $basename = basename($file, '.heartbeat');
            $transportId = substr($basename, strlen('listener-'));

            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $timestamp = (int) $content;

            if ($now - $timestamp <= 30) {
                $listeners[$transportId] = $timestamp;
            } else {
                @unlink($file);
            }
        }

        return $listeners;
    }
}
