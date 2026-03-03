<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports;

use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Contracts\Bridge\Redis;
use DeployTeam\Intercall\Services\MessageSerializer;
use DeployTeam\Intercall\Transports\Configuration\RedisInboundConfig;
use DeployTeam\Intercall\Transports\Contracts\InboundTransport;
use DeployTeam\Intercall\Transports\Contracts\SupportsDirectResponse;
use DeployTeam\Intercall\Transports\Contracts\TransportHasPrefix;
use Throwable;

use function Safe\pcntl_signal_dispatch;

class RedisInboundTransport implements InboundTransport, SupportsDirectResponse, TransportHasPrefix
{
    public function __construct(
        protected Redis $redis,
        protected MessageSerializer $serializer,
        protected Logger $logger,
        protected RedisInboundConfig $config,
    ) {}

    public function getId(): string
    {
        return $this->config->id ?? 'redis';
    }

    public function getPrefix(): string
    {
        return $this->config->prefix;
    }

    public function listen(string $channel, callable $callback, array $options = []): never
    {
        $this->logger->info('[Intercall Redis] Started listening', [
            'channel' => $channel,
        ]);

        while (true) { // @phpstan-ignore-line while.alwaysTrue - infinite loop is intentional for listener
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            try {
                $message = $this->redis->brpop($channel, $this->config->timeout);

                if ($message === null) {
                    continue;
                }

                $serialized = $message[1] ?? '';

                try {
                    $data = $this->serializer->deserialize($serialized);
                    $callback($data);
                } catch (Throwable $e) {
                    $this->logger->error('[Intercall Redis] Failed to process message', [
                        'channel' => $channel,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (Throwable $e) {
                $this->logger->error('[Intercall Redis] Listen error', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);

                usleep(100000);
            }
        }
    }

    public function isAvailable(): bool
    {
        try {
            $testKey = 'healthcheck';
            $this->redis->setex($testKey, 1, 'test');
            return $this->redis->exists($testKey);
        } catch (Throwable $e) {
            $this->logger->debug('[Intercall Redis] Connection check failed', [
                'host' => $this->config->host,
                'port' => $this->config->port,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendToChannel(string $channel, string $serializedData, int $ttl): void
    {
        $this->redis->lpush($channel, $serializedData);
        $this->redis->expire($channel, $ttl);
    }

    public function receiveFromChannel(string $channel, int $timeout): ?array
    {
        return $this->redis->blpop($channel, $timeout);
    }

    public function resetConnection(): void
    {
        $this->redis->disconnect();
    }
}
