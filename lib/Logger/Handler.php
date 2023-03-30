<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Logger;

abstract class Handler {
    protected array $levels;
    protected \DateTimeImmutable $timestamp;

    protected bool $_bubbles = true;
    protected string $_datetimeFormat = 'M d H:i:s';
    protected string|\Closure|null $_messageTransform = null;



    public function __construct(array $levels = [ 0, 1, 2, 3, 4, 5, 6, 7 ], array $options = []) {
        $levelsCount = count($levels);
        if ($levelsCount > 8) {
            throw new InvalidArgumentException(sprintf('Argument #%s ($levels) cannot have more than 8 values', $this->getParamPosition()));
        }
        if (count($levels) === 0) {
            throw new InvalidArgumentException(sprintf('Argument #%s ($levels) must not be empty', $this->getParamPosition()));
        }

        $levels = array_unique($levels, \SORT_NUMERIC);
        foreach ($levels as $k => $v) {
            if (!is_int($v)) {
                $type = gettype($v);
                $type = ($type === 'object') ? $v::class : $type;
                throw new InvalidArgumentException(sprintf('Value #%s of argument #%s ($levels) must be of type int, %s given', $k, $this->getParamPosition(), $type));
            }

            if ($v < 0 || $v > 7) {
                throw new RangeException(sprintf('Argument #%s ($levels) cannot be %s; it is not in the range 0 - 7', $this->getParamPosition(), $v));
            }
        }

        $this->levels = array_values($levels);

        foreach ($options as $key => $value) {
            $key = "_$key";
            $this->$key = $value;
        }
    }




    public function getOption(string $name): mixed {
        $class = get_class($this);
        if (!property_exists($class, "_$name")) {
            trigger_error(sprintf('Undefined option in %s: %s', $class, $name), \E_USER_WARNING);
            return null;
        }

        $name = "_$name";
        return $this->$name;
    }

    public function setOption(string $name, mixed $value): void {
        $class = get_class($this);
        if (!property_exists($class, "_$name")) {
            trigger_error(sprintf('Undefined option in %s: %s', $class, $name), \E_USER_WARNING);
        }

        $name = "_$name";
        $this->$name = $value;
    }

    public function __invoke(int $level, string $channel, string $message, array $context = []): void {
        $datetime = \DateTimeImmutable::createFromFormat('U.u', (string)microtime(true))->format($this->_datetimeFormat);

        $message = trim($message);
        if ($this->_messageTransform !== null) {
            $t = $this->_messageTransform;
            $message = $t($message, $context);
        }

        $this->invokeCallback($datetime, $level, $channel, $message, $context);
    }


    abstract protected function invokeCallback(string $datetime, int $level, string $channel, string $message, array $context = []): void;


    private function getParamPosition(): int {
        $params = (new \ReflectionClass(get_called_class()))->getConstructor()->getParameters();
        foreach ($params as $k => $p) {
            if ($p->getName() === 'levels') {
                return $k + 1;
            }
        }

        return -1;
    }
}