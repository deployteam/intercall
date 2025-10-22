<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Authentication;

class InvalidTokenException extends AuthenticationException
{
    public static function emptySecret(): self
    {
        return new self('Shared secret cannot be empty.');
    }

    public static function invalidFormat(): self
    {
        return new self('Invalid token format. Expected format: payload.signature');
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid token signature. Token may have been tampered with or wrong secret used.');
    }

    public static function invalidPayload(): self
    {
        return new self('Invalid token payload. Token data is corrupted.');
    }

    public static function missingTimestamp(): self
    {
        return new self('Token missing timestamp. Token is malformed.');
    }

    public static function missingNonce(): self
    {
        return new self('Token missing nonce. Token is malformed.');
    }

    public static function missingInRequest(): self
    {
        return new self('Missing authentication token in request.');
    }
}
