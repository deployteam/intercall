<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

interface IntercallEvent
{
    public function getEventName(): string;

    /**
     * @return TPayload
     */
    public function getPayload(): array;
}
