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
        $this->levels = $this->verifyLevels($levels);

        $class = get_class($this);
        foreach ($options as $key => $value) {
            $name = "_$key";
            if (!property_exists($class, $name)) {
                trigger_error(sprintf('Undefined option in %s: %s', $class, $key), \E_USER_WARNING);
                continue;
            }
            $this->$name = $value;
        }
    }



    public function getLevels(): array {
        return $this->levels;
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

    public function setLevels(int ...$levels): void {
        $this->levels = $this->verifyLevels($levels, false);
    }

    public function setOption(string $name, mixed $value): void {
        $class = get_class($this);
        if (!property_exists($class, "_$name")) {
            trigger_error(sprintf('Undefined option in %s: %s', $class, $name), \E_USER_WARNING);
            return;
        }

        $name = "_$name";
        $this->$name = $value;
    }

    public function __invoke(int $level, ?string $channel, string $message, array $context = []): void {
        if (!in_array($level, $this->levels)) {
            return;
        }

        $datetime = \DateTimeImmutable::createFromFormat('U.u', (string)microtime(true))->format($this->_datetimeFormat);

        $message = trim($message);
        if ($this->_messageTransform !== null) {
            $t = $this->_messageTransform;
            $message = $t($message, $context);
        }

        $this->invokeCallback($datetime, $level, $channel ?? '', $message, $context);
    }


    abstract protected function invokeCallback(string $datetime, int $level, string $channel, string $message, array $context = []): void;


    protected function verifyLevels(array $levels, bool $constructor = true): array {
        $levelsCount = count($levels);
        if (count($levels) === 0) {
            throw new InvalidArgumentException(sprintf('Argument #%s ($levels) must not be empty', ($constructor) ? $this->getParamPosition() : 1));
        }

        foreach ($levels as $k => $v) {
            if ($v instanceof Level) {
                $levels[$k] = $v = $v->value;
            }

            if (!is_int($v)) {
                $type = gettype($v);
                $type = ($type === 'object') ? $v::class : $type;
                $levelClassName = Level::class;
                throw new InvalidArgumentException(sprintf('Value #%s of argument #%s ($levels) must be of type int|%s, %s given', $k + 1, ($constructor) ? $this->getParamPosition() : 1, $levelClassName, $type));
            }

            if ($v < 0 || $v > 7) {
                throw new RangeException(sprintf('Value #%s of argument #%s ($levels) cannot be %s; it is not in the range 0 - 7', $k + 1, ($constructor) ? $this->getParamPosition() : 1, $v));
            }
        }

        $levels = array_unique($levels, \SORT_NUMERIC);
        sort($levels, \SORT_NUMERIC);
        return array_values($levels);
    }


    private function getParamPosition(): int {
        $params = (new \ReflectionClass(get_called_class()))->getConstructor()->getParameters();
        foreach ($params as $k => $p) {
            if ($p->getName() === 'levels') {
                return $k + 1;
            }
        }

        return -1; // @codeCoverageIgnore
    }
}