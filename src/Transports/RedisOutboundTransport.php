<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports;

use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Contracts\Bridge\Redis;
use DeployTeam\Intercall\Services\MessageSerializer;
use DeployTeam\Intercall\Transports\Configuration\RedisOutboundConfig;
use DeployTeam\Intercall\Transports\Contracts\OutboundTransport;
use DeployTeam\Intercall\Transports\Contracts\SupportsDirectResponse;
use DeployTeam\Intercall\Transports\Contracts\TransportHasPrefix;
use DeployTeam\Intercall\Transports\Results\AsyncTransportResult;
use DeployTeam\Intercall\Transports\Results\FailedTransportResult;
use DeployTeam\Intercall\Transports\Results\TransportResult;
use Throwable;

class RedisOutboundTransport implements OutboundTransport, SupportsDirectResponse, TransportHasPrefix
{
    public function __construct(
        protected Redis $redis,
        protected MessageSerializer $serializer,
        protected Logger $logger,
        protected RedisOutboundConfig $config,
    ) {}

    public function send(string $destination, array $data, array $options = []): TransportResult
    {
        $channel = $this->getChannel($destination);

        try {
            $serialized = $this->serializer->serialize($data);

            $result = $this->redis->lpush($channel, $serialized);

            if ($result === false || $result === 0) {
                $this->logger->error('[Intercall Redis] Failed to push message to queue', [
                    'destination' => $destination,
                    'channel' => $channel,
                ]);
                return new FailedTransportResult('Failed to push message to Redis queue');
            }

            $this->logger->debug('[Intercall Redis] Message sent', [
                'destination' => $destination,
                'channel' => $channel,
                'size' => strlen($serialized),
            ]);

            return new AsyncTransportResult();
        } catch (Throwable $e) {
            $this->logger->error('[Intercall Redis] Failed to send message', [
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);
            return new FailedTransportResult($e->getMessage());
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

    public function getPrefix(): string
    {
        return $this->config->prefix;
    }

    public function getTimeout(): int
    {
        return $this->config->timeout;
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

    protected function getChannel(string $destination): string
    {
        return "{$this->config->prefix}:{$destination}:requests";
    }
}
