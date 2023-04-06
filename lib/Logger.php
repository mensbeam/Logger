<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam;
use MensBeam\Logger\{
    ArgumentCountError,
    Handler,
    InvalidArgumentException,
    Level,
    StreamHandler,
    UnderflowException
};
use Psr\Log\LoggerInterface;


class Logger implements LoggerInterface {
    public const EMERGENCY = 0;
    public const ALERT = 1;
    public const CRITICAL = 2;
    public const ERROR = 3;
    public const WARNING = 4;
    public const NOTICE = 5;
    public const INFO = 6;
    public const DEBUG = 7;

    /**
     * Flag that causes warnings to be triggered when invalid throwables are in the
     * context array
     */
    public bool $warnOnInvalidContextThrowables = true;

    /** The channel name identifier used for this instance of Logger */
    protected ?string $channel;

    /**
     * Array of handlers the exceptions are passed to
     *
     * @var Handler[]
     */
    protected array $handlers = [];


    public function __construct(?string $channel = null, Handler ...$handlers) {
        $this->setChannel($channel);

        // Set some handlers if no handlers are set, printing lower levels to stderr,
        // higher to stdout.
        if (count($handlers) === 0) {
            $handlers = [
                new StreamHandler(stream: 'php://stderr', levels: [ 0, 1, 2, 3 ]),
                new StreamHandler(stream: 'php://stdout', levels: [ 4, 5, 6, 7 ])
            ];
        }

        $this->pushHandler(...$handlers);
    }



    public function getChannel(): ?string {
        return $this->channel;
    }

    public function getHandlers(): array {
        return $this->handlers;
    }

    public function popHandler(): Handler {
        if (count($this->handlers) === 1) {
            throw new UnderflowException('Popping the last handler will cause the Logger to have zero handlers; there must be at least one');
        }

        return array_pop($this->handlers);
    }

    public function pushHandler(Handler ...$handlers): void {
        if (count($handlers) === 0) {
            throw new ArgumentCountError(__METHOD__ . ' expects at least 1 argument, 0 given');
        }

        $this->handlers = [ ...$this->handlers, ...$handlers ];
    }

    public function setHandlers(Handler ...$handlers): void {
        $this->handlers = [];
        $this->pushHandler(...$handlers);
    }

    public function setChannel(?string $value): void {
        $this->channel = ($value !== null) ? substr($value, 0, 29) : null;
    }

    public function shiftHandler(): Handler {
        if (count($this->handlers) === 1) {
            throw new UnderflowException('Shifting the last handler will cause the Logger to have zero handlers; there must be at least one');
        }

        return array_shift($this->handlers);
    }

    public function unshiftHandler(Handler ...$handlers): void {
        if (count($handlers) === 0) {
            throw new ArgumentCountError(__METHOD__ . 'expects at least 1 argument, 0 given');
        }

        $this->handlers = [ ...$handlers, ...$this->handlers ];
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
     * @throws \MensBeam\Logger\InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = []): void {
        if ($level instanceof Level) {
            $level = $level->value;
        }

        // Because the interface won't allow limiting $level to just int|string this is
        // necessary.
        if (!is_int($level) && !is_string($level)) {
            $type = gettype($level);
            $type = ($type === 'object') ? $level::class : $type;
            throw new InvalidArgumentException(sprintf('Argument #1 ($level) must be of type int|%s|string, %s given', Level::class, $type));
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

        // The first paragraph states that we must be lenient with objects in the
        // context array, but the second paragraph says that exceptions MUST be in the
        // exception key and that we MUST verify it. They're contradictory. We
        // can't verify the exception while at the same time be lenient.

        // Our solution is to essentially violate the specification; we will remove
        // errant throwables and trigger warnings when encountered. Not issuing warnings
        // here provides a bad user experience. The user can be left in a situation
        // where it becomes difficult to ascertain why something isn't working. We do,
        // however, provide an easy way to suppress these warnings when necessary.
        foreach ($context as $k => $v) {
            if ($k === 'exception' && !$v instanceof \Throwable) {
                if ($this->warnOnInvalidContextThrowables) {
                    $type = gettype($v);
                    $type = ($type === 'object') ? $v::class : $type;
                    trigger_error(sprintf('The \'exception\' key in argument #3 ($context) can only contain values of type \Throwable, %s given', $type), \E_USER_WARNING);
                }
                unset($context[$k]);
            } elseif ($k !== 'exception' && $v instanceof \Throwable) {
                if ($this->warnOnInvalidContextThrowables) {
                    trigger_error(sprintf('Values of type %s can only be contained in the \'exception\' key in argument #3 ($context)', $v::class), \E_USER_WARNING);
                }
                unset($context[$k]);
            }
        }

        foreach ($this->handlers as $h) {
            $h($level, $this->channel, $message, $context);
            if (!$h->getOption('bubbles')) {
                break;
            }
        }
    }
}