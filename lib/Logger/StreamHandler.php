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
    protected ?string $url = null;
    protected ?string $urlScheme = null;

    protected ?string $_entryFormat = '%datetime%  %channel% %level_name%  %message%';


    public function __construct($stream = 'php://stdout', array $levels = [ 0, 1, 2, 3, 4, 5, 6, 7 ], array $options = []) {
        // Get the memory limit to determine the chunk size.

        // The memory limit is in a shorthand format
        // (https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes), so we
        // need it as a integer representation in bytes.
        if (preg_match('/^\s*(?<num>\d+)(?:\.\d+)?\s*(?<unit>[gkm])\s*$/i', strtolower(ini_get('memory_limit')), $matches) === 1) {
            $num = (int)$matches['num'];
            switch ($matches['unit'] ?? '') {
                case 'g': $num *= 1024;
                case 'm': $num *= 1024;
                case 'k': $num *= 1024;
            }

            // Use 10% of allowed memory or 100K, whichever is largest
            $this->chunkSize = min($this->chunkSize, max((int)($num / 10), 100 * 1024));
        }

        if (is_resource($stream)) {
            $this->resource = $stream;
            stream_set_chunk_size($this->resource, $this->chunkSize);
        } elseif(is_string($stream)) {
            $stream = Path::canonicalize($stream);
            // This wouldn't be useful for validating a URI schema, but it's fine for what this needs
            preg_match('/^(?:(?<scheme>[^:\s\/]+):)?(?<slashes>\/*)/i', $stream, $matches);
            if (in_array($matches['scheme'], [ 'file', '' ])) {
                $slashCount = strlen($matches['slashes'] ?? '');
                $relative = ($matches['scheme'] === 'file') ? ($slashCount === 0 || $slashCount === 2) : ($slashCount === 0);
                $stream = (($relative) ? getcwd() : '') . '/' . substr($stream, strlen($matches[0]));
            }

            $this->url = $stream;
            $this->urlScheme = $matches['scheme'] ?: 'file';
        } else {
            $type = gettype($stream);
            $type = ($type === 'object') ? $stream::class : $stream;
            throw new \TypeError(sprintf("Expected type 'resource|string'. Found '%s'", $type));
        }

        parent::__construct($levels, $options);
    }




    public function getStream() {
        return $this->resource ?? $this->url;
    }


    protected function invokeCallback(string $datetime, int $level, string $channel, string $message, array $context = []): void {
        if (!in_array($level, $this->levels)) {
            return;
        }

        // Do output formatting here.
        $output = trim(preg_replace_callback('/%([a-z_]+)%/', function($m) use ($datetime, $level, $channel, $message) {
            switch ($m[1]) {
                case 'channel': return $channel;
                case 'datetime': return $datetime;
                case 'level': return (string)$level;
                case 'level_name': return strtoupper(Level::from($level)->name);
                case 'message': return $message;
                default: return '';
            }
        }, $this->_entryFormat));
        // If output contains any newlines then add an additional newline to aid readability.
        if (str_contains($output, \PHP_EOL)) {
            $output .= \PHP_EOL;
        }
        $output .= \PHP_EOL;

        if ($this->resource === null) {
            if ($this->urlScheme === 'file') {
                Fs::mkdir(dirname($this->url));
            }

            $fp = fopen($this->url, 'a');
            stream_set_chunk_size($fp, $this->chunkSize);
            fwrite($fp, $output);
            fclose($fp);
        } else {
            fwrite($this->resource, $output);
        }
    }
}