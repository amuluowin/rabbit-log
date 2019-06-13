<?php


namespace rabbit\log;

/**
 * Class EchoTarget
 * @package rabbit\log
 */
class EchoTarget implements TargetInterface
{
    /**
     * @param array $messages
     * @param bool $flush
     */
    public function export(array $messages, bool $flush = true): void
    {
        foreach ($messages as $message) {
            echo implode(PHP_EOL, $messages) . PHP_EOL;
        }
    }
}