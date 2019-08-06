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
    /** @var AbstractTarget[] */
    protected $targetList = [];
    /** @var int */
    protected $tick = 0;
    /** @var int */
    protected $recall_depth = 0;

    /**
     * AbstractConfig constructor.
     * @param array $target
     */
    public function __construct(array $target)
    {
        $this->targetList = $target;
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
    abstract public function flush(bool $flush = false): void;
}