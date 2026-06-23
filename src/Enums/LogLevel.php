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

    public const int VERBOSITY_QUIET = 16;
    public const int VERBOSITY_NORMAL = 32;
    public const int VERBOSITY_VERBOSE = 64;
    public const int VERBOSITY_VERY_VERBOSE = 128;
    public const int VERBOSITY_DEBUG = 256;

    /**
     * @param int $verbosity The verbosity level from Symfony OutputInterface::VERBOSITY_*
     * @return self The corresponding log level
     */
    public static function fromVerbosity(int $verbosity): self
    {
        return match (true) {
            $verbosity >= self::VERBOSITY_DEBUG => self::DEBUG,
            $verbosity >= self::VERBOSITY_VERY_VERBOSE => self::INFO,
            $verbosity >= self::VERBOSITY_VERBOSE => self::WARNING,
            $verbosity >= self::VERBOSITY_NORMAL => self::ERROR,
            default => self::NONE,
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
