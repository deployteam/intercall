<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Exceptions\Serialization\SerializationException;
use MessagePack\MessagePack;

use function Safe\gzdecode;
use function Safe\gzencode;
use function Safe\json_decode;
use function Safe\json_encode;

class MessageSerializer
{
    private readonly bool $useNativeMsgpack;

    public function __construct(protected string $format = 'msgpack')
    {
        $this->useNativeMsgpack = extension_loaded('msgpack');
    }

    /** @param array<string, mixed> $data */
    public function serialize(array $data): string
    {
        return match ($this->format) {
            'msgpack' => $this->serializeMsgpack($data),
            'json' => json_encode($data, JSON_THROW_ON_ERROR),
            'gzip' => gzencode(json_encode($data, JSON_THROW_ON_ERROR), 9),
            default => throw SerializationException::unsupportedFormat($this->format),
        };
    }

    /** @return array<string, mixed> */
    public function deserialize(string $data, ?string $format = null): array
    {
        $format ??= $this->format;

        $result = match ($format) {
            'msgpack' => $this->deserializeMsgpack($data),
            'json' => json_decode($data, true, 512, JSON_THROW_ON_ERROR),
            'gzip' => (function () use ($data) {
                $decoded = gzdecode($data);
                return json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            })(),
            default => throw SerializationException::unsupportedFormat($format),
        };

        if (!is_array($result)) {
            throw SerializationException::invalidData('Deserialized data is not an array.');
        }

        return $result;
    }

    public function isUsingNativeMsgpack(): bool
    {
        return $this->useNativeMsgpack;
    }

    /** @param array<string, mixed> $data */
    protected function serializeMsgpack(array $data): string
    {
        if ($this->useNativeMsgpack) {
            return msgpack_pack($data);
        }

        return MessagePack::pack($data);
    }

    protected function deserializeMsgpack(string $data): mixed
    {
        if ($this->useNativeMsgpack) {
            return msgpack_unpack($data);
        }

        return MessagePack::unpack($data);
    }
}
