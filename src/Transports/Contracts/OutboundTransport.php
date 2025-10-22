<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Contracts;

use DeployTeam\Intercall\Transports\Results\TransportResult;

interface OutboundTransport extends Transport
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function send(string $destination, array $data, array $options = []): TransportResult;
}
