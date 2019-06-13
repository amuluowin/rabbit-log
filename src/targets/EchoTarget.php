<?php


namespace rabbit\log\targets;

/**
 * Class EchoTarget
 * @package rabbit\log\targets
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
            echo implode(PHP_EOL, $message) . PHP_EOL;
        }
    }
}