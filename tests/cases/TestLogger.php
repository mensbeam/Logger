<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger\Test;
use MensBeam\Logger,
    MensBeam\Logger\StreamHandler,
    Psr\Log\InvalidArgumentException;


/** @covers \MensBeam\Logger */
class TestLogger extends \PHPUnit\Framework\TestCase {
    public function testConstructor(): void {
        $l = new Logger();
        $this->assertNull($l->getChannel());
    }

    public function testDefaultHandler(): void {
        $l = new Logger();
        $h = $l->getHandlers();
        $this->assertEquals(1, count($h));
        $this->assertInstanceOf(StreamHandler::class, $h[0]);
        $s = $h[0]->getStream();
        $this->assertIsString($s);
        $this->assertSame('php://stdout', $s);
    }

    public function testSettingChannelAndHandlers(): void {
        $l = new Logger('oooooooooooooooooooooooooooook', new StreamHandler('ook.log'), new StreamHandler('eek.log'));
        $h = $l->getHandlers();
        // Should truncate the channel to 30 characters
        $this->assertSame('ooooooooooooooooooooooooooooo', $l->getChannel());
        $this->assertEquals(2, count($h));
        $this->assertSame(CWD . '/ook.log', $h[0]->getStream());
        $this->assertSame(CWD . '/eek.log', $h[1]->getStream());
    }

    /** @dataProvider provideLoggingTests */
    public function testLogging(string $levelName): void {
        $s = fopen('php://memory', 'r+');
        // Break after first handler to test breaking in Logger->log
        $l = new Logger('ook', new StreamHandler(stream: $s, options: [ 'bubbles' => false ]), new StreamHandler($s));
        $l->$levelName('Ook!');
        rewind($s);
        $o = stream_get_contents($s);
        $this->assertEquals(1, preg_match('/^' . (new \DateTimeImmutable())->format('M d') .  ' \d{2}:\d{2}:\d{2}  ook ' . strtoupper($levelName) . '  Ook!\n/', $o));
    }

    /** @dataProvider provideErrorTests */
    public function testErrors(string $throwableClassName, \Closure $closure): void {
        $this->expectException($throwableClassName);
        $closure(new Logger());
    }


    public static function provideLoggingTests(): iterable {
        foreach ([ 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ] as $l) {
            yield [ $l ];
        }
    }

    public static function provideErrorTests(): iterable {
        $iterable = [
            [
                \TypeError::class,
                function (Logger $l): void {
                    $l->log(3.14, 'Ook!');
                }
            ],
            [
                InvalidArgumentException::class,
                function (Logger $l): void {
                    $l->log(42, 'Ook!');
                }
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }
}