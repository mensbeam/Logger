[a]: https://www.php-fig.org/psr/psr-3/
[b]: https://www.php-fig.org/psr/psr-3/#12-message
[c]: https://www.php.net/manual/en/function.sprintf.php
[d]: https://www.php-fig.org/psr/psr-3/#13-context
[e]: https://www.php-fig.org/psr/psr-3/#11-basics
[f]: http://tools.ietf.org/html/rfc5424
[g]: https://code.mensbeam.com/MensBeam/Filesystem
[h]: https://code.mensbeam.com/MensBeam/Catcher
[i]: https://github.com/symfony/polyfill/tree/main/src/Ctype
[j]: https://github.com/symfony/polyfill/tree/main/src/Mbstring
[k]: https://github.com/php-fig/log

# Logger #

_Logger_ is a simple yet configurable logger for PHP. It is an opinionated implementation of [PSR-3 Logger Interface][a]. It uses classes called _handlers_ to handle messages to log. Currently there is only one handler: `StreamHandler` which allows for logging to files or to streams such as `php://stdout` or `php://stderr`. Handlers can be easily written and plugged into Logger.

## Opinionated? ##

This library attempts what we're calling an "opinionated" implementation of PSR-3. This is because while it successfully implements `Psr\Log\LoggerInterface` _Logger_ deviates from the true spirit of the specification in various ways:

1. In [section 1.1][e] PSR-3 states that when calling the `log` method with one of the log level constants (later shown to be in `Psr\Log\LogLevel`) it must have the same result as calling the level-specific methods. The log level constants in `Psr\Log\LogLevel` are strings, but the `$level` parameter of the `log` method in `Psr\Log\LoggerInterface` is typeless. The words of the specification suggest that the `$level` parameter should be a string, but the actual code implementors are to use doesn't specify a type. The same section also references [RFC 5424][f] when mentioning the log level methods, but why use strings when there are standardized integers used to identify severity? Since the interface allows for any type for `$level`, _Logger_ will prefer the actual RFC standard's integers but will accept and convert PSR-3's strings internally to the integers just so it can remain PSR-3 compatible.

2. In [section 1.2][b] of the specification it describes an optional feature, placeholders, and requires the implementor to write code to parse out and replace placeholders using a syntax and a method that's not present anywhere else in the entirety of PHP. _Logger_ won't support this feature because a logging library's place is to log messages and not to interpolate template strings. A separate library or a built-in function such as `sprintf` should be used instead. _Logger_ provides a way to transform messages that can be used to hook in a preferred interpolation method if desired, though.

3. The specification in [section 1.3][d] also specifies that if an `Exception` object is passed in the `$context` parameter it must be within an `exception` key. This makes sense, but curiously there's nary a mention of what to do with an `Error` object. They've existed since PHP 7 and can be thrown just like exceptions. _Logger_ will accept any `Throwable` in the `exception` key, but at present does nothing with it. Theoretically future handlers could be written to take advantage of it for structured data.

4. Also in the first item of [section 1.3][d] it states categorically that implementors must not trigger a warning when errant data is in the `$context` array and treat it with _"as much lenience as possible"_. It then states in the following item that if an exception is present in the context data it *must* be in the `exception` key and that implementors *must* verify the `exception` key. This is contradictory. You can't verify the `exception` key without triggering an error or exception when it's wrong. The user should be notified they've made a mistake; it's bad design otherwise. Our solution to this problem is to remove errant throwables from `$context` and also trigger warnings when they're encountered. However, `Logger->$warnOnInvalidContextThrowables` is provided to make it easy to suppress the warnings if necessary.

## Requirements ##

* PHP >= 8.1
* [mensbeam/filesystem][g] >= 1.0
    * ext-ctype or [symfony/polyfill-ctype][i] >= 1.8
    * ext-mbstring or [symfony/polyfill-mbstring][j] >= 1.8
* [psr/log][k] ^3.0

### Note ###

This library uses [mensbeam/filesystem][g] which provides polyfills for `ext-ctype` and `ext-mbstring`. If you have these extensions installed the polyfills won't be used. However, they are still installed. If you don't want the polyfills needlessly installed you can add this to your project's `composer.json`:

```json
{
    "require": {
        "ext-ctype": "*",
        "ext-mbstring": "*"
    },
    "provide": {
        "symfony/polyfill-ctype": "*",
        "symfony/polyfill-mbstring": "*"
    }
}
```

## Installation ##

```bash
composer require mensbeam/logger
```

## Usage ##

This library works without any configuration, but it might not be quite how you think it would work by default:

```php
use MensBeam\Logger;

$logger = new Logger();
```

This will create a logger that outputs all debug, info, notice, and warning entries to `STDOUT` while any error, critical, alert, and emergency entries are output to `STDERR`. This seems like it would be a bizarre default since it causes duplicate output to the shell on errors. However, if you accompany it with an error handler like [`Catcher`][h] it suddenly makes sense:

```php
use MensBeam\{
    Catcher,
    Logger
};
use MensBeam\Catcher\PlainTextHandler;

$catcher = new Catcher(new PlainTextHandler([
    'logger' => new Logger('log'),
    'silent' => true
]));
```

Now, _Logger_ will take care of the printing. But, _Logger_ can do far more.

## Documentation ##

### MensBeam\Logger ###

```php
namespace MensBeam;
use MensBeam\Logger\{
    Handler,
    Level
};


class Logger implements Psr\Log\LoggerInterface {
    public bool $warnOnInvalidContextThrowables = true;

    public function __construct(?string $channel = null, Handler ...$handlers);

    public function getChannel(): ?string;
    public function getHandlers(): array;
    public function popHandler(): Handler;
    public function pushHandler(Handler ...$handlers): void;
    public function setChannel(?string $value): void;
    public function setHandlers(Handler ...$handlers): void;
    public function shiftHandler(): Handler;
    public function unshiftHandler(Handler ...$handlers): void;

    public function emergency(string|\Stringable $message, array $context = []): void;
    public function alert(string|\Stringable $message, array $context = []): void;
    public function critical(string|\Stringable $message, array $context = []): void;
    public function error(string|\Stringable $message, array $context = []): void;
    public function warning(string|\Stringable $message, array $context = []): void;
    public function notice(string|\Stringable $message, array $context = []): void;
    public function info(string|\Stringable $message, array $context = []): void;
    public function debug(string|\Stringable $message, array $context = []): void;
    public function log(int|string|Level $level, string|\Stringable $message, array $context = []): void;
}
```

#### Properties ####

_warnOnInvalidContextThrowables_: When set to true Logger will trigger warnings when invalid `Throwable`s are in the `$context` array in the logging methods.  

#### MensBeam\Logger::getChannel ####

Returns the channel name for the instance of _Logger_.

#### MensBeam\Logger::getHandlers ####

Returns an array of the handlers defined for use in the _Logger_ instance.

#### MensBeam\Logger::popHandler ####

Pops the last handler off the stack and returns it

#### MensBeam\Logger::pushHandler ####

Pushes the specified handler(s) onto the stack

#### MensBeam\Logger::setChannel ####

Sets the channel to the specified string

#### MensBeam\Logger::setHandlers ####

Replaces the stack of handlers with those specified as parameters

#### MensBeam\Logger::shiftHandler ####

Shifts the first handler off the stack of handlers and returns it

#### MensBeam\Logger::unshiftHandler ####

Unshifts the specified handler(s) onto the beginning of the stack

#### MensBeam\Logger::emergency ####

Adds an emergency entry to the log

#### MensBeam\Logger::alert ####

Adds an alert entry to the log

#### MensBeam\Logger::critical ####

Adds a critical entry to the log

#### MensBeam\Logger::error ####

Adds an error entry to the log

#### MensBeam\Logger::warning ####

Adds a warning entry to the log

#### MensBeam\Logger::notice ####

Adds a notice entry to the log

#### MensBeam\Logger::info ####

Adds an info entry to the log

#### MensBeam\Logger::debug ####

Adds a debug entry to the log

#### MensBeam\Logger::log ####

Adds an entry to the log in the specified level


### MensBeam\Logger\Level ###

This is an enum of the RFC 5424 integer values for each of the log levels; also provides methods for converting to and from PSR-3 string values.

```php
namespace MensBeam;

enum Level: int {
    case Emergency = 0;
    case Alert = 1;
    case Critical = 2;
    case Error = 3;
    case Warning = 4;
    case Notice = 5;
    case Info = 6;
    case Debug = 7;

    public static function fromPSR3(string $level): self;
    public function toPSR3(): string;
}
```

#### MensBeam\Logger\Level::fromPSR3 ####

Takes a provided PSR-3 string level and returns a `Level` enum.

#### MensBeam\Logger\Level::toPSR3 ####

Returns a PSR-3 string level representation of the enum.


### MensBeam\Logger\Handler ###

Handlers inherit from this abstract class. Since it is an abstract class meant for constructing handlers protected methods and properties will be documented here as well.

```php
namespace MensBeam\Logger;

abstract class Handler {
    protected array $levels;

    protected bool $_bubbles = true;
    protected string|\Closure|null $_messageTransform = null;
    protected string $_timeFormat = 'M d H:i:s';

    public function __construct(array $levels = [ 0, 1, 2, 3, 4, 5, 6, 7 ], array $options = []);

    public function getLevels();
    public function getOption(string $name): mixed;
    public function setLevels(int ...$levels): void;
    public function setOption(string $name, mixed $value): void;
    public function __invoke(int $level, ?string $channel, string $message, array $context = []): void;

    abstract protected function invokeCallback(string $time, int $level, string $channel, string $message, array $context = []): void;
}
```

#### Properties (Protected) ####

_levels_: This is where the levels the handler is configured to support are stored  

#### Options ####

Properties which begin with an underscore are all options. They can be set either through the constructor or via `setHandler` by name, removing the underscore (\_) at the beginning. All handlers inherit these options. Options in inherited classes should also begin with an underscore (\_).

_bubbles_: When set to true the stack loop will continue onto the next handler; if false it won't. Defaults to _true_  
_messageTransform_: A callable to use to transform messages before outputting. Defaults to _null_  
_timeFormat_: The PHP-standard date format which to use for times in output. Defaults to _"M d H:i:s"_  

##### Message Transform #####

The _messageTransform_ option allows for manipulation of log messages. It accepts any callable with the following structure:

```php
function (string $message, array $context): string;
```

One common use of this feature would be to do string interpolation which isn't handled by the library. By providing a message transform it's possible to use any preferred method of interpolation:

```php
$handler = new StreamHandler(options: [
    'messageTransform' => function (string $message, array $context): string {
        return vsprintf($message, $context);
    }
]);
```

Of course this is a simplistic example. One would want to convert the `$context` array to numerical keys (or just use numerical keys) before usage in `vsprintf`, but as can be seen it's very possible.

#### MensBeam\Logger\Handler::getLevels ####

Returns the levels the handler is configured to support

#### MensBeam\Logger\Handler::getOption ####

Returns the value of the provided option name

#### MensBeam\Logger\Handler::setLevels ####

Sets the levels the handler will then support

#### MensBeam\Logger\Handler::setOption ####

Sets the provided option with the provided value

#### MensBeam\Logger\Handler::__invoke ####

Outputs/dispatches the log entry

#### MensBeam\Logger\Handler::invokeCallback (protected) ####

A callback method meant to be extended by inherited classes to output/dispatch the log entry


### MensBeam\Logger\StreamHandler ###

```php
namespace MensBeam\Logger;

class StreamHandler extends Handler {
    public function __construct(resource|string $stream = 'php://stdout', array $levels = [ 0, 1, 2, 3, 4, 5, 6, 7 ], array $options = []);

    public function getStream(): resource|string;
    public function setStream(resource|string $value): void;
}
```

#### Options ####

_entryFormat_: The format of the outputted log entry. Defaults to _"%time%  %channel% %level\_name%  %message%"_  

##### Entry Format #####

The following are recognized in the _entryFormat_ option:

| Format       | Description       | Example                                                                |
| ------------ | ----------------- | ---------------------------------------------------------------------- |
| %channel%    | channel name      | ook                                                                    |
| %level%      | log level integer | 1                                                                      |
| %level_name% | log level name    | ALERT                                                                  |
| %message%    | log message       | Uncaught Error: Call to undefined function ook() in /path/to/ook.php:7 |
| %time%       | timestamp         | Apr 08 09:58:12                                                        |

#### MensBeam\Logger\StreamHandler::getStream ####

Returns the resource or a URL where the handler will output to

#### MensBeam\Logger\StreamHandler::setStream ####

Sets where the handler will output to