<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Configuration;

use LogicException;

class HttpOutboundConfig implements TransportConfig
{
    public function __construct(public string $baseUrl, public int $timeout = 30) {}

    public function toArray(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
        ];
    }

    public static function fromArray(array $config): self
    {
        $baseUrl = $config['base_url'] ?? '';

        if ($baseUrl === '') {
            throw new LogicException('A base_url must be defined');
        }

        $timeout = $config['timeout'] ?? 30;
        assert(is_int($timeout));

        return new self(rtrim($baseUrl, '/'), $timeout);
    }
}
