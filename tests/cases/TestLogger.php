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
    InvalidArgumentException,
    Level,
    StreamHandler
};


/** @covers \MensBeam\Logger */
class TestLogger extends \PHPUnit\Framework\TestCase {
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
        $s = $h[0]->getStream();
        $this->assertIsString($s);
        $this->assertSame('php://stderr', $s);
        $s = $h[1]->getStream();
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
        $regex = '/^' . (new \DateTimeImmutable())->format('M d') .  ' \d{2}:\d{2}:\d{2}  ook ' . strtoupper($levelName) . '  Ook!\n/';
        $this->assertEquals(1, preg_match($regex, $o));

        // Try it again with Level enum, and for shits and giggles test removal
        // of errant throwables in context
        $l->log(constant(sprintf('%s::%s', Level::class, ucfirst($levelName))), 'Ook!', [ 'ook' => new \Exception('ook'), 'exception' => 'ook', 'eek' => 'Eek!' ]);
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
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }
}