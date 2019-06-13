<?php


namespace rabbit\log;

use rabbit\App;
use rabbit\contract\AbstractTimer;
use rabbit\server\WorkerHandlerInterface;

/**
 * Class FlushBufferHandler
 * @package rabbit\log
 */
class FlushBufferHandler implements WorkerHandlerInterface
{
    /**
     * @param int $worker_id
     * @throws \Exception
     */
    public function handle(int $worker_id): void
    {
        $logger = App::getLogger();
        if ($logger instanceof Logger && ($config = $logger->getConfig()) !== null && ($tick = $config->getTick()) > 0) {
            /** @var AbstractTimer $timer */
            $timer = getDI('timer');
            $timer->addTickTimer(get_class($logger) . '->flushlog', $tick * 1000,
                function () use ($config) {
                    $config->flush(true);
                });
        }
    }
}