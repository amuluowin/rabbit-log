<?php


namespace rabbit\log\targets;

/**
 * Class AbstractTarget
 * @package rabbit\log\targets
 */
abstract class AbstractTarget
{
    /** @var string */
    protected $split = ' | ';

    /**
     * AbstractTarget constructor.
     * @param string $split
     */
    public function __construct(string $split = '|')
    {
        $this->split = $split;
    }

    /**
     * @param array $messages
     * @param bool $flush
     */
    abstract public function export(array $messages, bool $flush = true): void;
}