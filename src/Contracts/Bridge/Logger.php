<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Contracts\Bridge;

use DeployTeam\Intercall\Enums\LogLevel;

interface Logger
{
    /**
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function info(string $message, array $context = []): void;

    /**
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function error(string $message, array $context = []): void;

    /**
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function warning(string $message, array $context = []): void;

    /**
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function debug(string $message, array $context = []): void;

    /**
     * @param LogLevel $level The minimum log level
     */
    public function setMinimumLevel(LogLevel $level): void;

    /**
     * @return LogLevel The current minimum level
     */
    public function getMinimumLevel(): LogLevel;
}
