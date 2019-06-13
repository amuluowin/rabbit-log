<?php


namespace rabbit\log\targets;

use rabbit\socket\pool\SocketPool;

/**
 * Class SeasStashTarget
 * @package rabbit\log\targets
 */
class SeasStashTarget implements TargetInterface
{
    /** @var SocketPool */
    private $clientPool;

    /**
     * SeasStashTarget constructor.
     * @param SocketPool $clientPool
     */
    public function __construct(SocketPool $clientPool)
    {
        $this->clientPool = $clientPool;
    }

    /**
     * @param array $messages
     * @param bool $flush
     */
    public function export(array $messages, bool $flush = true): void
    {
        $msg = '';
        foreach ($messages as $module => $message) {
            foreach ($message as $value) {
                $msg .= $module . '@' . $value . PHP_EOL;
            }
        }
        $connection = $this->clientPool->getConnection();
        $connection->send($msg);
        $connection->release();
    }
}