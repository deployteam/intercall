<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Authentication;

class ReplayAttackException extends AuthenticationException
{
    public static function tokenAlreadyUsed(): self
    {
        return new self('Token has already been used. This may indicate a replay attack.');
    }
}
