<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Enums;

enum RequestType: string
{
    case SYNC = 'sync';
    case ASYNC = 'async';
    case FIRE_AND_FORGET = 'fire_and_forget';
}
