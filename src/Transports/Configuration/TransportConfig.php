<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports\Configuration;

interface TransportConfig
{
    /** @return array<string, mixed> */
    public function toArray(): array;

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self;
}
