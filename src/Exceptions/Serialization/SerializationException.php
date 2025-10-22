<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Serialization;

use DeployTeam\Intercall\Exceptions\IntercallException;

class SerializationException extends IntercallException
{
    public static function unsupportedFormat(string $format): self
    {
        return new self("Unsupported serialization format: '{$format}'. Supported formats: msgpack, json, gzip");
    }

    public static function invalidData(string $reason = 'Deserialized data is not valid'): self
    {
        return new self($reason);
    }
}
