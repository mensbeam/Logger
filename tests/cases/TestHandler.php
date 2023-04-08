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
    Handler,
    InvalidArgumentException,
    Level,
    RangeException,
    StreamHandler
};


/** @covers \MensBeam\Logger\Handler */
class TestHandler extends ErrorHandlingTestCase {
    public function testConstructor(): void {
        // Test Level enums and integers, duplicated
        $h = new StreamHandler(levels: [
            Level::Notice,
            6,
            Level::Debug,
            Level::Info,
            Level::Critical,
            0,
            Level::Emergency,
            Level::Error,
            Level::Alert,
            3,
            Level::Warning
        ]);
        $this->assertSame([ 0, 1, 2, 3, 4, 5, 6, 7 ], $h->getLevels());
    }

    public function testOptions(): void {
        $h = new StreamHandler(options: [
            'bubbles' => false,
            'timeFormat' => 'Y-m-d\TH:i:sP'
        ]);
        $this->assertFalse($h->getOption('bubbles'));
        $this->assertSame('Y-m-d\TH:i:sP', $h->getOption('timeFormat'));
        $h->setOption('bubbles', true);
        $h->setOption('timeFormat', 'Y-m-d');
        $this->assertTrue($h->getOption('bubbles'));
        $this->assertSame('Y-m-d', $h->getOption('timeFormat'));
    }

    /** @dataProvider provideFatalErrorTests */
    public function testFatalErrors(string $throwableClassName, int $code, string $message, \Closure $closure): void {
        $this->expectException($throwableClassName);
        $this->expectExceptionMessage($message);
        if ($throwableClassName === Error::class) {
            $this->expectExceptionCode($code);
        }

        $closure(new StreamHandler());
    }

    /** @dataProvider provideNonFatalErrorTests */
    public function testNonFatalErrors(int $code, string $message, \Closure $closure): void {
        $closure(new StreamHandler());
        $this->assertEquals($code, $this->lastError?->getCode());
        $this->assertSame($message, $this->lastError?->getMessage());
    }


    public function testInvocation(): void {
        $s = fopen('php://memory', 'r+');
        // Test setting the timeFormat and messageTransform options, showing
        // a very simple example of using sprintf for interpolation.
        $l = new Logger('ook', new StreamHandler(stream: $s, options: [
            'timeFormat' => 'Y-m-d',
            'messageTransform' => function (string $message, array $context): string {
                return vsprintf($message, $context);
            }
        ]));
        $l->error('Ook! %s', [ 'Eek!' ]);
        rewind($s);
        $o = stream_get_contents($s);
        $this->assertEquals(1, preg_match('/^' . (new \DateTimeImmutable())->format('Y-m-d') .  '  ook ERROR  Ook! Eek!\n/', $o));
        fclose($s);
    }

    public function testInvocationWithUnsupportedLevel(): void {
        $s = fopen('php://memory', 'r+');
        $l = new Logger('ook', new StreamHandler(stream: $s, levels: [ 0 ]));
        $l->error('ook');
        rewind($s);
        $o = stream_get_contents($s);
        $this->assertSame('', $o);
        fclose($s);
    }


    public static function provideFatalErrorTests(): iterable {
        $iterable = [
            [
                InvalidArgumentException::class,
                0,
                'Argument #1 ($levels) must not be empty',
                function (Handler $h): void {
                    $h->setLevels();
                }
            ],
            [
                InvalidArgumentException::class,
                0,
                'Value #5 of argument #2 ($levels) must be of type int|MensBeam\Logger\Level, string given',
                function (Handler $h): void {
                    new StreamHandler(levels: [ 0, 1, 2, 3, '4', 5, 6, 7 ]);
                }
            ],
            [
                RangeException::class,
                0,
                'Value #2 of argument #1 ($levels) cannot be 42; it is not in the range 0 - 7',
                function (Handler $h): void {
                    $h->setLevels(0, 42);
                }
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }

    public static function provideNonFatalErrorTests(): iterable {
        $iterable = [
            [
                \E_USER_WARNING,
                'Undefined option in ' . StreamHandler::class . ': ook',
                function (Handler $h): void {
                    $h = new StreamHandler(options: [ 'ook' => 'eek' ]);
                }
            ],
            [
                \E_USER_WARNING,
                'Undefined option in ' . StreamHandler::class . ': ook',
                function (Handler $h): void {
                    $ook = $h->getOption('ook');
                }
            ],
            [
                \E_USER_WARNING,
                'Undefined option in ' . StreamHandler::class . ': ook',
                function (Handler $h): void {
                    $ook = $h->setOption('ook', 'eek');
                }
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }
}