<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Contracts\IntercallErrorResponse;
use DeployTeam\Intercall\Contracts\IntercallExceptionMapper;
use Throwable;

class ConventionExceptionMapper implements IntercallExceptionMapper
{
    public function map(Throwable $exception): IntercallErrorResponse
    {
        if (method_exists($exception, 'toIntercallError')) {
            $result = $exception->toIntercallError();

            if ($result instanceof IntercallErrorResponse) {
                return $result;
            }
        }

        return new IntercallErrorResponse(IntercallErrorResponse::UNHANDLED, $exception->getMessage());
    }
}
