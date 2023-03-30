[a]: https://www.php-fig.org/psr/psr-3/
[b]: https://www.php-fig.org/psr/psr-3/#12-message
[c]: https://www.php.net/manual/en/function.sprintf.php
[d]: https://www.php-fig.org/psr/psr-3/#13-context
[e]: https://www.php-fig.org/psr/psr-3/#11-basics
[f]: http://tools.ietf.org/html/rfc5424

# Logger #

_Logger_ is a simple yet configurable logger for PHP. It is an opinionated implementation of [PSR-3 Logger Interface][a]. It uses classes called _handlers_ to handle messages to log. Currently there is only one handler: `StreamHandler` which allows for logging to files or to streams such as `php://stdout` or `php://stderr`. Handlers can be easily written and plugged into Logger.

## Opinionated? ##

This library attempts what we're calling an "opinionated" implementation of PSR-3. This is because while it successfully implements `Psr\Log\LoggerInterface` Logger deviates from the true spirit of the specification in various ways:

1. In [section 1.1][e] PSR-3 states that when calling the `log` method with one of the log level constants (later shown to be in `Psr\Log\LogLevel`) it must have the same result as calling the level-specific methods. The log level constants in `Psr\Log\LogLevel` are strings, but the `$level` parameter of the `log` method in `Psr\Log\LoggerInterface` is typeless. The words of the specification suggest that the `$level` parameter should be a string, but the actual code implementors are to use doesn't specify a type. The same section also references [RFC 5424][f] when mentioning the log level methods, but why use strings when there are standardized integers used to identify severity? Since the interface allows for any type for `$level`, _Logger_ will prefer the actual RFC standard's integers but will accept and convert PSR-3's strings internally to the integers just so it can remain PSR-3 compatible.

2. In [section 1.2][b] of the specification it describes an optional feature, placeholders, and requires the implementor to write code to parse out and replace placeholders using a syntax and a method that's not present anywhere else in the entirety of PHP. _Logger_ won't support this feature because a logging library's place is to log messages and not to interpolate template strings. A separate library or a built-in function such as `sprintf` should be used instead. _Logger_ provides a way to transform messages that can be used to hook in a preferred interpolation method if desired, though.

3. The specification in [section 1.3][d] also specifies that if an `Exception` object is passed in the `$context` parameter it must be within an `exception` key. This makes sense, but curiously there's nary a mention of what to do with an `Error` object. They've existed since PHP 7 and can be thrown just like exceptions. _Logger_ will accept any `Throwable` in the `exception` key, but at present does nothing with it. Theoretically future handlers could be written to take advantage of it for structured data.
