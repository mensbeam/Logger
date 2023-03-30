<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger;

class RangeException extends \RangeException {
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null) {
        // Make output a bit more useful by making it show the file and line of where the constructor was called.
        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $b = null;
        foreach ($backtrace as $k => $v) {
            if ($v['function'] === '__construct' && $v['class'] === __NAMESPACE__ . '\Handler') {
                $b = $backtrace[$k + 1] ?? null;
                break;
            }
        }
        if ($b !== null) {
            $this->file = $b['file'];
            $this->line = $b['line'];
        }

        parent::__construct($message, $code, $previous);
    }
}