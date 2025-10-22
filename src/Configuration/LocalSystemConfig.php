<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Configuration;

use DeployTeam\Intercall\Transports\TransportChain;

class LocalSystemConfig
{
    /** @var array<string, Token> */
    private array $tokens = [];

    public function __construct(
        public readonly string $name,
        public readonly TransportChain $transports,
    ) {}

    /** @param array<int, string>|string|null $forSystems */
    public function registerToken(string $token, array|string $forSystems): self
    {
        $systems = $forSystems ?? '*';

        $this->tokens[] = new Token($token, $systems);

        return $this;
    }

    /** @return array<int, Token> */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return array<int, Token>
     */
    public function getTokensForSystem(string $system): array
    {
        return array_filter(
            $this->tokens,
            fn(Token $it) => $it->isAllowedForSystem($system),
        );
    }
}
