<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger;
use Psr\Log\LogLevel;


enum Level: int {
    /** System is unusable */
    case Emergency = 0;

    /** Action must be taken immediately */
    case Alert = 1;

    /**
     * Critical conditions.
     * Example: Application component unavailable, unexpected exception.
     */
    case Critical = 2;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    case Error = 3;

    /** Exceptional occurrences that are not errors. */
    case Warning = 4;

    /** Normal but significant events. */
    case Notice = 5;

    /**
     * Interesting events.
     * Example: User logs in, SQL logs.
     */
    case Info = 6;

    /** Detailed debug information. */
    case Debug = 7;


    public static function fromPSR3(string $level): self {
        return match ($level) {
            LogLevel::EMERGENCY => self::Emergency,
            LogLevel::ALERT => self::Alert,
            LogLevel::CRITICAL => self::Critical,
            LogLevel::ERROR => self::Error,
            LogLevel::WARNING => self::Warning,
            LogLevel::NOTICE => self::Notice,
            LogLevel::INFO => self::Info,
            LogLevel::DEBUG => self::Debug
        };
    }

    public function toPSR3(): string {
        return match ($this) {
            self::Emergency => LogLevel::EMERGENCY,
            self::Alert => LogLevel::ALERT,
            self::Critical => LogLevel::CRITICAL,
            self::Error => LogLevel::ERROR,
            self::Warning => LogLevel::WARNING,
            self::Notice => LogLevel::NOTICE,
            self::Info => LogLevel::INFO,
            self::Debug => LogLevel::DEBUG
        };
    }
}