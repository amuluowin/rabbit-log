<?php


namespace rabbit\log\targets;

/**
 * Interface TargetInterface
 * @package rabbit\log\targets
 */
interface TargetInterface
{
    /**
     * @param array $messages
     * @param bool $flush
     */
    public function export(array $messages, bool $flush = true): void;
}