<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger\Test;
use MensBeam\Logger;
use MensBeam\Logger\Level,
    Psr\Log\LogLevel;


/** @covers \MensBeam\Logger\Level */
class TestLevel extends \PHPUnit\Framework\TestCase {
    /** @dataProvider provideConversionsTests */
    public function testConversions(string $PSR3Level, Level $level): void {
        $this->assertSame($level, Level::fromPSR3($PSR3Level));
        $this->assertSame($PSR3Level, $level->toPSR3());
    }

    public static function provideConversionsTests(): iterable {
        foreach ([
            [ LogLevel::EMERGENCY, Level::Emergency ],
            [ LogLevel::ALERT, Level::Alert ],
            [ LogLevel::CRITICAL, Level::Critical ],
            [ LogLevel::ERROR, Level::Error ],
            [ LogLevel::WARNING, Level::Warning ],
            [ LogLevel::NOTICE, Level::Notice ],
            [ LogLevel::INFO, Level::Info ],
            [ LogLevel::DEBUG, Level::Debug ]
        ] as $l) {
            yield $l;
        }
    }
}