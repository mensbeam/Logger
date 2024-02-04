<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger\Test;
use org\bovigo\vfs\vfsStream;
use MensBeam\Logger\{
    InvalidArgumentException,
    Level,
    StreamHandler,
    TypeError
};
use PHPUnit\Framework\{
    TestCase,
    Attributes\CoversClass,
    Attributes\DataProvider
};


#[CoversClass('MensBeam\Logger\StreamHandler')]
class TestStreamHandler extends TestCase {

    #[DataProvider('provideResourceTypesTests')]
    public function testResourceTypes(\Closure $closure): void {
        $regex = '/^' . (new \DateTimeImmutable())->format('M d') .  ' \d{2}:\d{2}:\d{2}  ook ERROR  Ook!\nEek!\n/';
        $this->assertEquals(1, preg_match($regex, $closure()));
    }

    public function testGetStream(): void {
        $h = new StreamHandler('ook');
        $this->assertNull($h->getStream());
        $v = vfsStream::setup('ook', 0777, [ 'ook.log' => '' ]);
        $f = $v->url() . '/ook.log';
        $h = new StreamHandler(fopen($f, 'a'));
        $this->assertTrue(is_resource($h->getStream()));
    }

    public function testGetURI(): void {
        $h = new StreamHandler('ook');
        $this->assertSame(CWD . '/ook', $h->getURI());
    }

    #[DataProvider('provideEntryTransformingTests')]
    public function testEntryTransforming(\Closure $entryTransform, string $regex): void {
        $s = fopen('php://memory', 'r+');
        $h = new StreamHandler(stream: $s, options: [
            'entryTransform' => $entryTransform
        ]);
        $h(Level::Error->value, 'ook', 'ook');
        rewind($s);
        $o = stream_get_contents($s);
        $this->assertMatchesRegularExpression($regex, $o);
        fclose($s);
    }

    #[DataProvider('provideFatalErrorTests')]
    public function testFatalErrors(string $throwableClassName, int $code, string $message, \Closure $closure): void {
        $this->expectException($throwableClassName);
        $this->expectExceptionMessage($message);
        if ($throwableClassName === Error::class) {
            $this->expectExceptionCode($code);
        }

        $s = fopen('php://memory', 'r+');
        $closure(new StreamHandler($s));
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

    public static function provideEntryTransformingTests(): iterable {
        $iterable = [
            [
                function (): string {
                    return '';
                },
                '/\n/'
            ],
            [
                function (string $time, int $level, string $levelName, string $channel): string {
                    return "$channel $channel $channel $channel $level $levelName";
                },
                '/ook ook ook ook 3 Error\n/'
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }

    public static function provideFatalErrorTests(): iterable {
        $iterable = [
            [
                InvalidArgumentException::class,
                0,
                'Argument #1 ($value) must be of type resource|string, integer given',
                function (StreamHandler $h): void {
                    new StreamHandler(42);
                }
            ],
            [
                InvalidArgumentException::class,
                0,
                'Argument #1 ($value) must be of type resource|string, DateTimeImmutable given',
                function (StreamHandler $h): void {
                    new StreamHandler(new \DateTimeImmutable());
                }
            ],
            [
                TypeError::class,
                0,
                'Value of entryTransform option must be callable, integer given',
                function (StreamHandler $h): void {
                    $h->setOption('entryTransform', 42);
                }
            ],
            [
                TypeError::class,
                0,
                'Value of entryTransform option must be callable, DateTimeImmutable given',
                function (StreamHandler $h): void {
                    $h->setOption('entryTransform', new \DateTimeImmutable());
                }
            ],
            [
                TypeError::class,
                0,
                'Return value of entryTransform option callable must be a string, integer given',
                function (StreamHandler $h): void {
                    $h->setOption('entryTransform', fn() => 42);
                    $h(Level::Error->value, 'fail', 'fail');
                }
            ],
            [
                TypeError::class,
                0,
                'Return value of entryTransform option callable must be a string, DateTimeImmutable given',
                function (StreamHandler $h): void {
                    $h->setOption('entryTransform', fn() => new \DateTimeImmutable());
                    $h(Level::Error->value, 'fail', 'fail');
                }
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }
}