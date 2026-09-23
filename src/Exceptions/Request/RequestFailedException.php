<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Exceptions\Request;

use DeployTeam\Intercall\Contracts\IntercallErrorResponse;

class RequestFailedException extends RequestException
{
    public ?IntercallErrorResponse $remoteError = null;

    public static function forSystem(string $targetSystem, string $reason = ''): self
    {
        $message = "Failed to send request to system '{$targetSystem}'.";
        if ($reason !== '') {
            $message .= " Reason: {$reason}";
        }
        return new self($message);
    }

    public static function fromRemote(IntercallErrorResponse $error): self
    {
        $instance = new self($error->message);
        $instance->remoteError = $error;
        return $instance;
    }
}
