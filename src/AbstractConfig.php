<?php


namespace rabbit\log;

use rabbit\log\targets\AbstractTarget;

/**
 * Interface ConfigInterface
 * @package rabbit\log
 */
abstract class AbstractConfig
{
    /** @var int */
    protected $bufferSize = 1;
    /** @var array */
    protected $buffer = [];
    /** @var AbstractTarget[] */
    protected $targetList = [];
    /** @var int */
    protected $tick = 0;

    protected $group;

    /**
     * AbstractConfig constructor.
     * @param array $target
     */
    public function __construct(array $target)
    {
        $this->targetList = $target;
        $this->group = waitGroup();
    }

    /**
     * @return float
     */
    public function getTick(): float
    {
        return $this->tick;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    abstract public function log(string $level, string $message, array $context = []): void;

    /**
     * @param bool $flush
     */
    public function flush(bool $flush = false): void
    {
        if (!empty($this->buffer) && $flush || ($this->bufferSize !== 0 && $this->bufferSize <= count($this->buffer))) {
            $buffer = $this->buffer;
            $this->buffer = [];
            foreach ($this->targetList as $index => $target) {
                $this->group->add($index, function () use ($target, $buffer, $flush) {
                    $target->export($buffer, $flush);
                });
            }
            $this->group->wait();
            unset($buffer);
        }
    }
}