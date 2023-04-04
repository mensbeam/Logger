<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger\Test;
use MensBeam\Logger,
    org\bovigo\vfs\vfsStream;
use MensBeam\Logger\{
    InvalidArgumentException,
    Level,
    StreamHandler
};


/** @covers \MensBeam\Logger\StreamHandler */
class TestStreamHandler extends ErrorHandlingTestCase {
    /** @dataProvider provideResourceTypesTests */
    public function testResourceTypes(\Closure $closure): void {
        $regex = '/^' . (new \DateTimeImmutable())->format('M d') .  ' \d{2}:\d{2}:\d{2}  ook ERROR  Ook!\nEek!\n/';
        $this->assertEquals(1, preg_match($regex, $closure()));
    }

    public function testFatalErrors(): void {
        $this->expectException(InvalidArgumentException::class);
        new StreamHandler(42);
    }

    public function testGetStream(): void {
        $h = new StreamHandler('ook');
        $this->assertSame(CWD . '/ook', $h->getStream());
    }

    /** @dataProvider provideEntryFormattingTests */
    public function testEntryFormatting(string $entryFormat, string $regex): void {
        $s = fopen('php://memory', 'r+');
        $h = new StreamHandler(stream: $s, options: [
            'entryFormat' => $entryFormat
        ]);
        $h(Level::Error->value, 'ook', 'ook');
        rewind($s);
        $o = stream_get_contents($s);
        $this->assertEquals(1, preg_match($regex, $o));
        fclose($s);
    }


    public static function provideResourceTypesTests(): iterable {
        $iterable = [
            function (): string {
                $s = fopen('php://memory', 'r+');
                $h = new StreamHandler($s);
                $h(Level::Error->value, 'ook', "Ook!\nEek!");
                rewind($s);
                $o = stream_get_contents($s);
                fclose($s);
                return $o;
            },
            function (): string {
                $f = tempnam(sys_get_temp_dir(), 'logger');
                $h = new StreamHandler("file://$f");
                $h(Level::Error->value, 'ook', "Ook!\nEek!");
                $o = file_get_contents($f);
                unlink($f);
                return $o;
            },
            function (): string {
                $v = vfsStream::setup('ook', 0777, [ 'ook.log' => '' ]);
                $f = $v->url() . '/ook.log';
                $h = new StreamHandler($f);
                $h(Level::Error->value, 'ook', "Ook!\nEek!");
                return file_get_contents($f);
            }
        ];

        foreach ($iterable as $i) {
            yield [ $i ];
        }
    }

    public static function provideEntryFormattingTests(): iterable {
        $iterable = [
            [ '%ook%', '/\n/' ],
            [ '%channel% %channel% %channel% %channel% %level% %level_name%', '/ook ook ook ook 3 ERROR\n/' ],
            [ '', '/^' . (new \DateTimeImmutable())->format('M d') .  ' \d{2}:\d{2}:\d{2}  ook ERROR  ook\n/' ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }
}