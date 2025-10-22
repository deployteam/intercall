<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports;

use DeployTeam\Intercall\Exceptions\Transport\NoTransportAvailableException;
use DeployTeam\Intercall\Transports\Contracts\InboundTransport;
use DeployTeam\Intercall\Transports\Contracts\Transport;

class TransportChain
{
    /** @var array<int, Transport> */
    private array $transports = [];

    /** @var array<string, Transport> */
    private array $transportsById = [];

    public function register(Transport $transport): self
    {
        $this->transports[] = $transport;

        if ($transport instanceof InboundTransport) {
            $this->transportsById[$transport->getId()] = $transport;
        }

        return $this;
    }

    /** @return array<int, Transport> */
    public function getTransports(): array
    {
        return $this->transports;
    }

    /**
     * @throws NoTransportAvailableException
     */
    public function getById(string $id): Transport
    {
        $transport = $this->transportsById[$id] ?? null;

        if ($transport === null) {
            throw NoTransportAvailableException::notRegisteredWithId($id);
        }

        return $transport;
    }
}
