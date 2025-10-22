<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Request;

class RequestFailedException extends RequestException
{
    public static function forSystem(string $targetSystem, string $reason = ''): self
    {
        $message = "Failed to send request to system '{$targetSystem}'.";
        if ($reason !== '') {
            $message .= " Reason: {$reason}";
        }
        return new self($message);
    }
}
