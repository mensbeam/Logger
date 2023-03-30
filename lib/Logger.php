<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam;
use MensBeam\Logger\{
    Handler,
    Level,
    StreamHandler
};
use Psr\Log\{
    InvalidArgumentException,
    LoggerInterface
};


class Logger implements LoggerInterface {
    public const EMERGENCY = 0;
    public const ALERT = 1;
    public const CRITICAL = 2;
    public const ERROR = 3;
    public const WARNING = 4;
    public const NOTICE = 5;
    public const INFO = 6;
    public const DEBUG = 7;

    /** The channel name identifier used for this instance of Logger */
    protected ?string $channel;

    /**
     * Array of handlers the exceptions are passed to
     *
     * @var Handler[]
     */
    protected array $handlers;


    public function __construct(?string $channel = null, Handler ...$handlers) {
        $this->setChannel($channel);

        if (count($handlers) === 0) {
            $handlers[] = new StreamHandler('php://stdout');
        }

        $this->handlers = $handlers;
    }



    public function getChannel(): ?string {
        return $this->channel;
    }

    public function setChannel(?string $value): void {
        $this->channel = ($value !== null) ? substr($value, 0, 29) : null;
    }

    public function getHandlers(): array {
        return $this->handlers;
    }


    /** System is unusable. */
    public function emergency(string|\Stringable $message, array $context = []): void {
        $this->log(Level::Emergency->value, $message, $context);
    }

    /** Action must be taken immediately. */
    public function alert(string|\Stringable $message, array $context = []): void {
        $this->log(Level::Alert->value, $message, $context);
    }

    /**
     * Critical conditions.
     * Example: Application component unavailable, unexpected exception.
     */
    public function critical(string|\Stringable $message, array $context = []): void {
        $this->log(Level::Critical->value, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public function error(string|\Stringable $message, array $context = []): void {
        $this->log(Level::Error->value, $message, $context);
    }

    /** Exceptional occurrences that are not errors. */
    public function warning(string|\Stringable $message, array $context = []): void {
        $this->log(Level::Warning->value, $message, $context);
    }

    /** Normal but significant events. */
    public function notice(string|\Stringable $message, array $context = []): void {
        $this->log(Level::Notice->value, $message, $context);
    }

    /**
     * Interesting events.
     * Example: User logs in, SQL logs.
     */
    public function info(string|\Stringable $message, array $context = []): void {
        $this->log(Level::Info->value, $message, $context);
    }

    /** Detailed debug information. */
    public function debug(string|\Stringable $message, array $context = []): void {
        $this->log(Level::Debug->value, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = []): void {
        // Because the interface won't allow limiting $level to just int|string this is
        // necessary.
        if (!is_int($level) && !is_string($level)) {
            $type = gettype($level);
            $type = ($type === 'object') ? $level::class : $type;
            throw new \TypeError(sprintf("Expected type 'int|string'. Found '%s'.", $type));
        }

        // If the level is a string convert it to a RFC5424 level integer.
        $origLevel = $level;
        $level = (is_string($level)) ? Level::fromPSR3($level) : $level;
        if ($level < 0 || $level > 7) {
            throw new InvalidArgumentException(sprintf('Invalid log level %s', $origLevel));
        }

        # PSR-3: Logger Interface
        # §1.3 Context
        #
        # * Every method accepts an array as context data. This is meant to hold any
        #   extraneous information that does not fit well in a string. The array can
        #   contain anything. Implementors MUST ensure they treat context data with as
        #   much lenience as possible. A given value in the context MUST NOT throw an
        #   exception nor raise any php error, warning or notice.
        #
        # * If an Exception object is passed in the context data, it MUST be in the
        #   'exception' key. Logging exceptions is a common pattern and this allows
        #   implementors to extract a stack trace from the exception when the log
        #   backend supports it. Implementors MUST still verify that the 'exception' key
        #   is actually an Exception before using it as such, as it MAY contain
        #   anything.

        // We're not doing interpolation :)

        foreach ($this->handlers as $h) {
            $h($level, $this->channel, $message, $context);
            if (!$h->getOption('bubbles')) {
                break;
            }
        }
    }
}