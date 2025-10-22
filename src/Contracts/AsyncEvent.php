<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts;

interface AsyncEvent
{
    /** @return class-string<CallbackHandler>|null */
    public function getCallbackHandler(): ?string;
}
