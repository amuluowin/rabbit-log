<?php


namespace rabbit\log\targets;

use rabbit\helper\ArrayHelper;
use rabbit\helper\StringHelper;
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
     * @throws \rabbit\core\Exception
     */
    public function export(array $messages): void
    {
        /** @var AbstractTcpConnection $connection */
        $connection = $this->clientPool->getConnection();
        foreach ($messages as $module => $message) {
            foreach ($message as $msg) {
                if (is_string($msg)) {
                    switch (ini_get('seaslog.appender')) {
                        case '2':
                        case '3':
                            $msg = trim(substr($msg, StringHelper::str_n_pos($msg, ' ', 6)));
                            break;
                        case '1':
                        default:
                            $fileName = basename($module);
                            $module = substr($fileName, 0, strpos($fileName, '_', -1));
                    }
                    $msg = explode($this->split, trim($msg));
                }
                if (!empty($this->levelList) && !in_array(strtolower($msg[$this->levelIndex]), $this->levelList)) {
                    continue;
                }
                ArrayHelper::remove($msg, '%c');
                $msg = $module . '@' . str_replace(PHP_EOL, '', implode($this->split, $msg)) . PHP_EOL;
                $connection->send($msg);
            }
        }
        $connection->release();
    }
}
