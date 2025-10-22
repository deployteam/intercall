<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

interface CallbackHandler
{
    /** @return class-string<IntercallEvent<array<string, mixed>>> */
    public function handles(): string;

    public function onSuccess(): void;

    public function onFailure(): void;
}
