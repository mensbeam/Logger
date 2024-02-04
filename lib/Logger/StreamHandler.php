<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger;
use MensBeam\{
    Filesystem as Fs,
    Path
};


class StreamHandler extends Handler {
    protected int $chunkSize = 10 * 1024 * 1024;
    protected $resource = null;
    protected ?string $uri = null;
    protected ?string $uriScheme = null;

    protected $_entryTransform = null;


    public function __construct($stream = 'php://stdout', array $levels = [ 0, 1, 2, 3, 4, 5, 6, 7 ], array $options = []) {
        // Get the memory limit to determine the chunk size.

        // The memory limit is in a shorthand format
        // (https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes), so we
        // need it as a integer representation in bytes.
        if (preg_match('/^\s*(?<num>\d+)(?:\.\d+)?\s*(?<unit>[gkm])\s*$/i', ini_get('memory_limit'), $matches) === 1) {
            $num = (int)$matches['num'];
            switch (strtolower($matches['unit'] ?? '')) {
                case 'g': $num *= 1024;
                case 'm': $num *= 1024;
                case 'k': $num *= 1024;
            }

            // Use 10% of allowed memory or 100K, whichever is largest
            $this->chunkSize = min($this->chunkSize, max((int)($num / 10), 100 * 1024));
        }

        $this->setStream($stream);
        parent::__construct($levels, $options);
    }




    public function getStream() {
        return $this->resource;
    }

    public function getURI(): ?string {
        return $this->uri;
    }

    public function setOption(string $name, mixed $value): void {
        if ($name === 'entryTransform' && !is_callable($value)) {
            $type = gettype($value);
            $type = ($type === 'object') ? $value::class : $type;
            throw new TypeError(sprintf('Value of entryTransform option must be callable, %s given', $type));
        }

        parent::setOption($name, $value);
    }

    public function setStream($value): void {
        $isResource = is_resource($value);
        if (!$isResource && !is_string($value)) {
            $type = gettype($value);
            $type = ($type === 'object') ? $value::class : $type;
            throw new InvalidArgumentException(sprintf('Argument #1 ($value) must be of type resource|string, %s given', $type));
        } elseif ($isResource) {
            $this->resource = $value;
            $value = stream_get_meta_data($value)['uri'] ?? null;
            stream_set_chunk_size($this->resource, $this->chunkSize);
        }

        if ($value !== null) {
            $value = Path::canonicalize($value);
            // This wouldn't be useful for validating a URI schema, but it's fine for what this needs
            preg_match('/^(?:(?<scheme>[^:\s\/]+):)?(?<slashes>\/*)/i', $value, $matches);
            if (in_array($matches['scheme'], [ 'file', '' ])) {
                $slashCount = strlen($matches['slashes'] ?? '');
                $relative = ($matches['scheme'] === 'file') ? ($slashCount === 0 || $slashCount === 2) : ($slashCount === 0);
                $value = (($relative) ? getcwd() : '') . '/' . substr($value, strlen($matches[0]));
            }
            $this->uriScheme = $matches['scheme'] ?: 'file';
        }

        $this->uri = $value;
    }


    protected function invokeCallback(string $time, int $level, string $channel, string $message, array $context = []): void {
        if ($this->_entryTransform !== null) {
            $output = call_user_func($this->_entryTransform, $time, $level, Level::from($level)->name, $channel, $message, $context);
            if (!is_string($output)) {
                $type = gettype($output);
                $type = ($type === 'object') ? $output::class : $type;
                throw new TypeError(sprintf('Return value of entryTransform option callable must be a string, %s given', $type));
            }
        } else {
            $output = sprintf('%s  %s %s  %s', $time, $channel, strtoupper(Level::from($level)->name), $message);
        }

        // If output contains any newlines then add an additional newline to aid readability.
        if (str_contains($output, \PHP_EOL)) {
            $output .= \PHP_EOL;
        }
        $output .= \PHP_EOL;

        if ($this->uriScheme === 'file') {
            Fs::mkdir(dirname($this->uri));
        }

        if ($this->resource === null) {
            $this->resource = fopen($this->uri, 'a');
            stream_set_chunk_size($this->resource, $this->chunkSize);
        }
        fwrite($this->resource, $output);
    }


    public function __destruct() {
        if (!is_resource($this->resource)) {
            return;
        }
        fclose($this->resource);
    }
}