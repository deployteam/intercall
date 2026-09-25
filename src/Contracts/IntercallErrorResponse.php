<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

final readonly class IntercallErrorResponse
{
    public const string UNHANDLED = 'error.unhandled';

    /** @param array<string, mixed> $context */
    public function __construct(
        public string $code,
        public string $message,
        public array $context = [],
    ) {}
}
