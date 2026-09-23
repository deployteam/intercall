<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

use Throwable;

interface IntercallExceptionMapper
{
    public function map(Throwable $exception): IntercallErrorResponse;
}
