<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\Bridge\Redis;
use DeployTeam\Intercall\Exceptions\Authentication\InvalidTokenException;
use DeployTeam\Intercall\Exceptions\Authentication\ReplayAttackException;
use DeployTeam\Intercall\Exceptions\Authentication\TokenExpiredException;

use function Safe\base64_decode;
use function Safe\json_decode;
use function Safe\json_encode;

class IntercallAuth
{
    public function __construct(
        protected Redis $redis,
        protected int $tokenTtl = 300,
        protected string $redisPrefix = 'intercall',
    ) {}

    /** @param array<string, mixed> $payload */
    public function generateToken(string $sharedSecret, array $payload = []): string
    {
        if ($sharedSecret === '') {
            throw InvalidTokenException::emptySecret();
        }

        $nonce = bin2hex(random_bytes(16));
        $timestamp = time();

        $tokenPayload = array_merge($payload, [
            'timestamp' => $timestamp,
            'nonce' => $nonce,
        ]);

        $encodedPayload = base64_encode(json_encode($tokenPayload, JSON_THROW_ON_ERROR));
        $signature = $this->generateSignature($encodedPayload, $sharedSecret);

        return $encodedPayload . '.' . $signature;
    }

    /** @return array<string, mixed> */
    public function verifyToken(string $token, string $sharedSecret): array
    {
        if ($sharedSecret === '') {
            throw InvalidTokenException::emptySecret();
        }

        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            throw InvalidTokenException::invalidFormat();
        }

        [$encodedPayload, $signature] = $parts;

        $expectedSignature = $this->generateSignature($encodedPayload, $sharedSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            throw InvalidTokenException::invalidSignature();
        }

        $payload = json_decode(base64_decode($encodedPayload, true), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw InvalidTokenException::invalidPayload();
        }

        if (!isset($payload['timestamp'])) {
            throw InvalidTokenException::missingTimestamp();
        }

        $timestamp = $payload['timestamp'];
        assert(is_int($timestamp));
        $age = time() - $timestamp;
        if ($age > $this->tokenTtl) {
            throw TokenExpiredException::expired();
        }

        if ($age < -60) {
            throw TokenExpiredException::futureTimestamp();
        }

        if (!isset($payload['nonce'])) {
            throw InvalidTokenException::missingNonce();
        }

        if ($this->isNonceUsed($payload['nonce'])) {
            throw ReplayAttackException::tokenAlreadyUsed();
        }

        $this->markNonceAsUsed($payload['nonce'], $this->tokenTtl);

        return $payload;
    }

    protected function generateSignature(string $data, string $secret): string
    {
        return hash_hmac('sha256', $data, $secret);
    }

    protected function isNonceUsed(string $nonce): bool
    {
        $key = "{$this->redisPrefix}:token:used:{$nonce}";

        return $this->redis->exists($key);
    }

    protected function markNonceAsUsed(string $nonce, int $ttl): void
    {
        $key = "{$this->redisPrefix}:token:used:{$nonce}";

        $this->redis->setex($key, $ttl, '1');
    }
}
