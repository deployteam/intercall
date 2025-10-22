<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Configuration;

use LogicException;

class RedisInboundConfig implements TransportConfig
{
    public function __construct(
        public string $id,
        public string $host,
        public int $port = 6379,
        public ?string $password = null,
        public int $database = 0,
        public string $prefix = '',
        public int $timeout = 1,
    ) {}


    public function toArray(): array
    {
        return [
            'id' => $this->id,
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
        $id = $config['id'] ?? null;
        $host = $config['host'];
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;
        $prefix = $config['prefix'] ?? '';
        $timeout = $config['timeout'] ?? 1;

        if ($id === null) {
            throw new LogicException('Property "id" is required');
        }

        if ($host === null) {
            throw new LogicException('Property "host" is required');
        }

        return new self(
            $id,
            $host,
            $port,
            $password,
            $database,
            $prefix,
            $timeout,
        );
    }
}
