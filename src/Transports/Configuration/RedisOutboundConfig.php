<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Configuration;

use LogicException;

class RedisOutboundConfig implements TransportConfig
{
    public function __construct(
        public string $host = '127.0.0.1',
        public int $port = 6379,
        public ?string $password = null,
        public int $database = 0,
        public string $prefix = 'intercall',
        public int $timeout = 30,
    ) {}

    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'password' => $this->password,
            'database' => $this->database,
            'prefix' => $this->prefix,
            'timeout' => $this->timeout,
        ];
    }

    public static function fromArray(array $config): self
    {
        $host = $config['host'] ?? null;
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;
        $prefix = $config['prefix'] ?? 'intercall';
        $timeout = $config['timeout'] ?? null;

        if ($host === null) {
            throw new LogicException('Required "host" parameter is required');
        }

        return new self(
            $host,
            $port,
            $password,
            $database,
            $prefix,
            $timeout,
        );
    }
}
