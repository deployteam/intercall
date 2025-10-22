<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Factories;

use DeployTeam\Intercall\Contracts\Bridge\HttpClient;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Transports\Configuration\HttpOutboundConfig;
use DeployTeam\Intercall\Transports\HttpOutboundTransport;

class HttpOutboundTransportFactory
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly Logger $logger,
    ) {}

    public function create(HttpOutboundConfig $config): HttpOutboundTransport
    {
        return new HttpOutboundTransport(
            $this->httpClient,
            $this->logger,
            $config,
        );
    }
}
