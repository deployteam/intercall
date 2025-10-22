<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Enums;

enum AsyncStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
}
