<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\IntercallErrorResponse;

final readonly class CachedResponse
{
    public function __construct(
        public mixed $result,
        public ?IntercallErrorResponse $error,
    ) {}
}
