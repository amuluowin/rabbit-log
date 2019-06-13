<?php


namespace rabbit\log\targets;

/**
 * Class EchoTarget
 * @package rabbit\log\targets
 */
class EchoTarget extends AbstractTarget
{
    /**
     * @param array $messages
     * @param bool $flush
     */
    public function export(array $messages, bool $flush = true): void
    {
        foreach ($messages as $message) {
            foreach ($message as $msg) {
                echo implode($this->split, $msg) . PHP_EOL;
            }
        }
    }
}