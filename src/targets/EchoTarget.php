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
                if (is_string($msg)) {
                    $msg = explode($this->split, trim($msg));
                }
                if (!empty($this->levelList) && !in_array(strtolower($msg[$this->levelIndex]), $this->levelList)) {
                    continue;
                }
                echo implode($this->split, $msg) . PHP_EOL;
            }
        }
    }
}
