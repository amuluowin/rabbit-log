<?php


namespace rabbit\log;

/**
 * Interface TargetInterface
 * @package rabbit\log
 */
interface TargetInterface
{
    /**
     * @param array $messages
     * @param bool $flush
     */
    public function export(array $messages, bool $flush = true): void;
}