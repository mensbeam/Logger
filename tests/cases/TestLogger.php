<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger\Test;
use MensBeam\Logger;
use MensBeam\Logger\{
    ArgumentCountError,
    InvalidArgumentException,
    Level,
    StreamHandler,
    UnderflowException
};


/** @covers \MensBeam\Logger */
class TestLogger extends ErrorHandlingTestCase {
    public function testConstructor(): void {
        $l = new Logger();
        $this->assertNull($l->getChannel());
    }

    public function testDefaultHandler(): void {
        $l = new Logger();
        $h = $l->getHandlers();
        $this->assertEquals(2, count($h));
        $this->assertInstanceOf(StreamHandler::class, $h[0]);
        $this->assertInstanceOf(StreamHandler::class, $h[1]);
        $s = $h[0]->getURI();
        $this->assertIsString($s);
        $this->assertSame('php://stderr', $s);
        $s = $h[1]->getURI();
        $this->assertIsString($s);
        $this->assertSame('php://stdout', $s);
    }

    public function testSettingChannelAndHandlers(): void {
        $l = new Logger('oooooooooooooooooooooooooooook', new StreamHandler('ook.log'), new StreamHandler('eek.log'));
        $h = $l->getHandlers();
        // Should truncate the channel to 30 characters
        $this->assertSame('ooooooooooooooooooooooooooooo', $l->getChannel());
        $this->assertEquals(2, count($h));
        $this->assertSame(CWD . '/ook.log', $h[0]->getURI());
        $this->assertSame(CWD . '/eek.log', $h[1]->getURI());
    }

    public function testHandlerStackManipulation(): void {
        $l = new Logger();
        $l->pushHandler(new StreamHandler(stream: 'ook'));

        $h = $l->getHandlers();
        $this->assertEquals(3, count($h));
        $this->assertInstanceOf(StreamHandler::class, $h[0]);
        $this->assertInstanceOf(StreamHandler::class, $h[1]);
        $this->assertInstanceOf(StreamHandler::class, $h[2]);
        $this->assertSame(CWD . '/ook', end($h)->getURI());

        $l->setHandlers(
            new StreamHandler(stream: 'ook'),
            new StreamHandler(stream: 'eek')
        );
        $h = $l->getHandlers();
        $this->assertEquals(2, count($h));
        $this->assertSame(CWD . '/ook', $h[0]->getURI());
        $this->assertSame(CWD . '/eek', $h[1]->getURI());

        $h1 = $l->shiftHandler();
        $this->assertSame($h[0], $h1);
        $h = $l->getHandlers();
        $this->assertSame(CWD . '/eek', $h[0]->getURI());
        $this->assertEquals(1, count($l->getHandlers()));

        $l->unshiftHandler($h1);
        $h = $l->getHandlers();
        $this->assertSame($h[0], $h1);
        $this->assertSame(CWD . '/ook', $h[0]->getURI());
        $this->assertEquals(2, count($h));

        $h2 = $l->popHandler();
        $this->assertSame($h[1], $h2);
        $h = $l->getHandlers();
        $this->assertSame(CWD . '/ook', $h[0]->getURI());
        $this->assertEquals(1, count($l->getHandlers()));
    }

    /** @dataProvider provideLoggingTests */
    public function testLogging(string $levelName): void {
        $s = fopen('php://memory', 'r+');
        // Break after first handler to test breaking in Logger->log
        $l = new Logger('ook', new StreamHandler(stream: $s, options: [ 'bubbles' => false ]), new StreamHandler($s));
        $l->$levelName('Ook!');
        rewind($s);
        $o = stream_get_contents($s);
        $regex = '/^' . (new \DateTimeImmutable())->format('M d') .  ' \d{2}:\d{2}:\d{2}  ook ' . strtoupper($levelName) . '  Ook!\n/';
        $this->assertEquals(1, preg_match($regex, $o));

        $l->log(constant(sprintf('%s::%s', Level::class, ucfirst($levelName))), 'Ook!');
        rewind($s);
        $o = stream_get_contents($s);
        $this->assertEquals(1, preg_match($regex, $o));
        fclose($s);
    }

    /** @dataProvider provideFatalErrorTests */
    public function testFatalErrors(string $throwableClassName, \Closure $closure): void {
        $this->expectException($throwableClassName);
        $closure(new Logger());
    }

    /** @dataProvider provideNonFatalErrorTests */
    public function testNonFatalErrors(int $code, string $message, \Closure $closure): void {
        $closure(new Logger());
        $this->assertEquals($code, $this->lastError?->getCode());
        $this->assertSame($message, $this->lastError?->getMessage());
    }


    public static function provideLoggingTests(): iterable {
        foreach ([ 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ] as $l) {
            yield [ $l ];
        }
    }

    public static function provideFatalErrorTests(): iterable {
        $iterable = [
            [
                InvalidArgumentException::class,
                function (Logger $l): void {
                    $l->log(3.14, 'Ook!');
                }
            ],
            [
                InvalidArgumentException::class,
                function (Logger $l): void {
                    $l->log(42, 'Ook!');
                }
            ],
            [
                UnderflowException::class,
                function (Logger $l): void {
                    $l->popHandler();
                    $l->popHandler();
                }
            ],
            [
                ArgumentCountError::class,
                function (Logger $l): void {
                    $l->pushHandler();
                }
            ],
            [
                UnderflowException::class,
                function (Logger $l): void {
                    $l->shiftHandler();
                    $l->shiftHandler();
                }
            ],
            [
                ArgumentCountError::class,
                function (Logger $l): void {
                    $l->unshiftHandler();
                }
            ],
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }

    public static function provideNonFatalErrorTests(): iterable {
        $iterable = [
            [
                \E_USER_WARNING,
                'The \'exception\' key in argument #3 ($context) can only contain values of type \Throwable, string given',
                function (Logger $l): void {
                    $l->setHandlers(new StreamHandler('php://memory'));
                    $l->error('ook', [ 'exception' => 'ook' ]);
                }
            ],
            [
                \E_USER_WARNING,
                'Values of type Exception can only be contained in the \'exception\' key in argument #3 ($context)',
                function (Logger $l): void {
                    $l->setHandlers(new StreamHandler('php://memory'));
                    $l->error('ook', [ 'ook' => new \Exception('ook') ]);
                }
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }
}