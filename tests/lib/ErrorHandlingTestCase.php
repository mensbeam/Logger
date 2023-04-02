<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger\Test;


class ErrorHandlingTestCase extends \PHPUnit\Framework\TestCase {
    protected ?Error $lastError = null;


    public function setUp(): void {
        set_error_handler([ $this, 'handleError' ]);
    }

    public function tearDown(): void {
        restore_error_handler();
    }

    public function handleError(int $code, string $message, string $file, int $line): void {
        $e = new Error($message, $code);
        $this->lastError = $e;
        if (in_array($code, [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR, \E_RECOVERABLE_ERROR ])) {
            throw $e;
        }
    }
}