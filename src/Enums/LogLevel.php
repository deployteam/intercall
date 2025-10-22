<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Enums;

enum LogLevel: string
{
    case NONE = 'none';
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
    case DEBUG = 'debug';

    /**
     * @return int The priority value
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::ERROR => 1,
            self::WARNING => 2,
            self::INFO => 3,
            self::DEBUG => 4,
        };
    }

    /**
     * @param LogLevel $minimumLevel The minimum level to log
     * @return bool True if this level should be logged
     */
    public function shouldLog(LogLevel $minimumLevel): bool
    {
        return $this->getPriority() <= $minimumLevel->getPriority();
    }

    /**
     * @param int $verbosity The verbosity level from OutputInterface::VERBOSITY_*
     * @return self The corresponding log level
     */
    public static function fromVerbosity(int $verbosity): self
    {
        return match ($verbosity) {
            0 => self::NONE,           // VERBOSITY_QUIET
            1 => self::ERROR,          // VERBOSITY_NORMAL
            2 => self::WARNING,        // VERBOSITY_VERBOSE (-v)
            3 => self::INFO,           // VERBOSITY_VERY_VERBOSE (-vv)
            default => self::DEBUG,    // VERBOSITY_DEBUG (-vvv)
        };
    }

    /**
     * @param string $level The level string (e.g., 'info', 'debug', 'error')
     * @return self The log level
     */
    public static function fromString(string $level): self
    {
        return match (strtolower($level)) {
            'none' => self::NONE,
            'error' => self::ERROR,
            'warning', 'warn' => self::WARNING,
            'info' => self::INFO,
            'debug' => self::DEBUG,
            default => self::INFO,
        };
    }
}
