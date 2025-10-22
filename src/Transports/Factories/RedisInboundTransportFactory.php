<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Factories;

use DeployTeam\Intercall\Bridge\GenericRedis;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Services\MessageSerializer;
use DeployTeam\Intercall\Transports\Configuration\RedisInboundConfig;
use DeployTeam\Intercall\Transports\RedisInboundTransport;

class RedisInboundTransportFactory
{
    public function __construct(
        private readonly MessageSerializer $serializer,
        private readonly Logger $logger,
    ) {}

    public function create(RedisInboundConfig $config): RedisInboundTransport
    {
        $redis = new GenericRedis([
            'host' => $config->host,
            'port' => $config->port,
            'password' => $config->password,
            'database' => $config->database,
            'prefix' => $config->prefix,
            'timeout' => $config->timeout,
        ]);

        return new RedisInboundTransport(
            $redis,
            $this->serializer,
            $this->logger,
            $config,
        );
    }
}
