<?php


namespace rabbit\log\targets;

use rabbit\helper\ArrayHelper;
use rabbit\socket\pool\SocketPool;
use rabbit\socket\tcp\AbstractTcpConnection;

/**
 * Class SeasStashTarget
 * @package rabbit\log\targets
 */
class SeasStashTarget extends AbstractTarget
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
        /** @var AbstractTcpConnection $connection */
        $connection = $this->clientPool->getConnection();
        foreach ($messages as $module => $message) {
            foreach ($message as $value) {
                ArrayHelper::remove($value, '%c');
                $msg = $module . '@' . str_replace(PHP_EOL, '', implode($this->split, $value)) . PHP_EOL;
                $connection->send($msg);
            }
        }
        $connection->release();
    }
}