<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts\Bridge;

interface HttpClient
{
    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponse;
}
