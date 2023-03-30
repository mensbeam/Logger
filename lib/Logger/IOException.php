<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger;

class IOException extends \RuntimeException {
    protected ?string $path;

    public function __construct(string $message, int $code = 0, \Throwable $previous = null, string $path = null) {
        $this->path = $path;

        parent::__construct($message, $code, $previous);
    }

    public function getPath(): ?string {
        return $this->path;
    }
}