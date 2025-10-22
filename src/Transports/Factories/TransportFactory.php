<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Factories;

use DeployTeam\Intercall\Transports\Configuration\HttpOutboundConfig;
use DeployTeam\Intercall\Transports\Configuration\RedisInboundConfig;
use DeployTeam\Intercall\Transports\Configuration\RedisOutboundConfig;
use DeployTeam\Intercall\Transports\Configuration\TransportConfig;
use DeployTeam\Intercall\Transports\Contracts\InboundTransport;
use DeployTeam\Intercall\Transports\Contracts\OutboundTransport;
use LogicException;

class TransportFactory
{
    public function __construct(
        private readonly RedisInboundTransportFactory $redisInboundFactory,
        private readonly RedisOutboundTransportFactory $redisOutboundFactory,
        private readonly HttpOutboundTransportFactory $httpOutboundFactory,
    ) {}

    public function createOutbound(TransportConfig $config): OutboundTransport
    {
        return match (true) {
            $config instanceof RedisOutboundConfig => $this->redisOutboundFactory->create($config),
            $config instanceof HttpOutboundConfig => $this->httpOutboundFactory->create($config),
            default => throw new LogicException(
                'Unknown transport config type: ' . $config::class,
            ),
        };
    }

    public function createInbound(TransportConfig $config): InboundTransport
    {
        return match (true) {
            $config instanceof RedisInboundConfig => $this->redisInboundFactory->create($config),
            default => throw new LogicException(
                'Unknown or unsupported inbound transport config type: ' . $config::class,
            ),
        };
    }
}
