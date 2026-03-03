<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\Redis;
use DeployTeam\Intercall\Exceptions\Transport\RedisConnectionException;
use Predis\Client as PredisClient;
use Redis as PhpRedis;

class GenericRedis implements Redis
{
    private PhpRedis|PredisClient|null $client = null;
    private readonly bool $useNativePhpRedis;

    /**
     * @param array<string, mixed> $config
     * @param string|null $driver
     * @throws RedisConnectionException
     */
    public function __construct(protected array $config, ?string $driver = null)
    {
        $this->useNativePhpRedis = $this->shouldUsePhpRedis($driver);
    }

    protected function getClient(): PhpRedis|PredisClient
    {
        if ($this->client === null) {
            $this->client = $this->createClient();
        }

        return $this->client;
    }

    public function isUsingNativePhpRedis(): bool
    {
        return $this->useNativePhpRedis;
    }

    public function lpush(string $key, string $value): int|false
    {
        if ($this->useNativePhpRedis) {
            /** @var PhpRedis $client */
            $client = $this->getClient();
            return $client->lPush($key, $value);
        }

        /** @var PredisClient $client */
        $client = $this->getClient();
        return $client->lpush($key, [$value]);
    }

    /**
     * @param string|array<string> $keys
     * @return array<int, string>|null
     */
    public function brpop(string|array $keys, int $timeout): ?array
    {
        if ($this->useNativePhpRedis) {
            /** @var PhpRedis $client */
            $client = $this->getClient();
            $result = $client->brPop($keys, $timeout);
            if ($result === false || !is_array($result) || empty($result)) {
                return null;
            }
            if (!isset($result[0]) || !isset($result[1])) {
                return null;
            }
            /** @var array<int, string> $result */
            return $result;
        }

        /** @var PredisClient $client */
        $client = $this->getClient();
        $result = $client->brpop($keys, $timeout);
        if ($result === null || !is_array($result) || empty($result)) {
            return null;
        }
        if (!isset($result[0]) || !isset($result[1])) {
            return null;
        }
        return [(string) $result[0], (string) $result[1]];
    }

    /**
     * @param string|array<string> $keys
     * @return array<int, string>|null
     */
    public function blpop(string|array $keys, int $timeout): ?array
    {
        if ($this->useNativePhpRedis) {
            /** @var PhpRedis $client */
            $client = $this->getClient();
            $result = $client->blPop($keys, $timeout);
            if ($result === false || !is_array($result) || empty($result)) {
                return null;
            }
            if (!isset($result[0]) || !isset($result[1])) {
                return null;
            }
            /** @var array<int, string> $result */
            return $result;
        }

        /** @var PredisClient $client */
        $client = $this->getClient();
        $result = $client->blpop($keys, $timeout);
        if ($result === null || !is_array($result) || empty($result)) {
            return null;
        }
        if (!isset($result[0]) || !isset($result[1])) {
            return null;
        }
        return [(string) $result[0], (string) $result[1]];
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        if ($this->useNativePhpRedis) {
            return (bool) $this->getClient()->setex($key, $ttl, $value);
        }

        return (bool) $this->getClient()->setex($key, $ttl, $value);
    }

    public function get(string $key): ?string
    {
        $result = $this->getClient()->get($key);
        if ($result === false || $result === null) {
            return null;
        }
        assert(is_string($result));
        return $result;
    }

    public function exists(string $key): bool
    {
        return (bool) $this->getClient()->exists($key);
    }

    public function incr(string $key): int
    {
        return $this->getClient()->incr($key);
    }

    public function expire(string $key, int $ttl): bool
    {
        return (bool) $this->getClient()->expire($key, $ttl);
    }

    public function ttl(string $key): int
    {
        return $this->getClient()->ttl($key);
    }

    public function publish(string $channel, string $message): int
    {
        return $this->getClient()->publish($channel, $message);
    }

    /**
     * @return array<int, string>
     */
    public function keys(string $pattern): array
    {
        if ($this->useNativePhpRedis) {
            /** @var PhpRedis $client */
            $client = $this->getClient();
            $keys = $client->keys($pattern);
            return is_array($keys) ? $keys : [];
        }

        /** @var PredisClient $client */
        $client = $this->getClient();
        $keys = $client->keys($pattern);
        return is_array($keys) ? $keys : [];
    }

    public function del(string $key): int
    {
        return (int) $this->getClient()->del($key);
    }

    public function disconnect(): void
    {
        if ($this->client instanceof PhpRedis) {
            try {
                $this->client->close();
            } catch (\Throwable) {
            }
        }

        $this->client = null;
    }

    /**
     * @param string|null $driver
     * @throws RedisConnectionException
     */
    protected function shouldUsePhpRedis(?string $driver): bool
    {
        $phpredisAvailable = extension_loaded('redis');
        $predisAvailable = class_exists(PredisClient::class);

        if ($driver !== null) {
            if ($driver === 'phpredis' && !$phpredisAvailable) {
                throw RedisConnectionException::phpredisNotInstalled();
            }
            if ($driver === 'predis' && !$predisAvailable) {
                throw RedisConnectionException::predisNotInstalled();
            }
            if ($driver !== 'phpredis' && $driver !== 'predis') {
                throw RedisConnectionException::invalidDriver($driver);
            }
            return $driver === 'phpredis';
        }

        if ($phpredisAvailable) {
            return true;
        }

        if ($predisAvailable) {
            return false;
        }

        throw RedisConnectionException::noDriverAvailable();
    }

    /**
     * @return PhpRedis|PredisClient
     */
    protected function createClient(): PhpRedis|PredisClient
    {
        if ($this->useNativePhpRedis) {
            return $this->createPhpRedisClient();
        }

        return $this->createPredisClient();
    }

    /**
     * @return PhpRedis
     * @throws RedisConnectionException
     */
    protected function createPhpRedisClient(): PhpRedis
    {
        $redis = new PhpRedis();

        $host = $this->config['host'];
        $port = $this->config['port'] ?? 6379;
        $timeout = $this->config['timeout'] ?? 0.0;
        $readTimeout = $this->config['read_timeout'] ?? 0.0;

        $connected = $redis->connect($host, $port, $timeout, null, 0, $readTimeout);
        if (!$connected) {
            throw RedisConnectionException::failedToConnect($host, $port);
        }

        if (array_key_exists('password', $this->config)) {
            $redis->auth($this->config['password']);
        }

        if (array_key_exists('database', $this->config)) {
            $redis->select($this->config['database']);
        }

        return $redis;
    }

    /**
     * @return PredisClient
     */
    protected function createPredisClient(): PredisClient
    {
        $parameters = [
            'scheme' => $this->config['scheme'] ?? 'tcp',
            'host' => $this->config['host'],
            'port' => $this->config['port'] ?? 6379,
        ];

        if (array_key_exists('password', $this->config)) {
            $parameters['password'] = $this->config['password'];
        }

        if (array_key_exists('database', $this->config)) {
            $parameters['database'] = $this->config['database'];
        }

        if (array_key_exists('timeout', $this->config)) {
            $parameters['timeout'] = $this->config['timeout'];
        }

        if (array_key_exists('read_timeout', $this->config)) {
            $parameters['read_write_timeout'] = $this->config['read_timeout'];
        }

        $options = [];
        if (array_key_exists('prefix', $this->config)) {
            $options['prefix'] = $this->config['prefix'];
        }

        return new PredisClient($parameters, $options);
    }
}
