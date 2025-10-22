<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Configuration;

readonly class Token
{
    /** @param array<int, string>|string $whitelist */
    public function __construct(public string $value, public array|string $whitelist = '*') {}

    public function isAllowedForSystem(string $systemName): bool
    {
        if ($this->allowsAllSystems()) {
            return true;
        }

        if (is_array($this->whitelist)) {
            return in_array($systemName, $this->whitelist, true);
        }

        return $this->whitelist === $systemName;
    }

    public function allowsAllSystems(): bool
    {
        return $this->whitelist === '*';
    }
}
