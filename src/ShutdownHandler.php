<?php


namespace rabbit\log;


use rabbit\App;
use rabbit\server\WorkerHandlerInterface;

/**
 * Class ShutdownHandler
 * @package rabbit\log
 */
class ShutdownHandler implements WorkerHandlerInterface
{
    /**
     * @param int $worker_id
     */
    public function handle(int $worker_id): void
    {
        register_shutdown_function(function () {
            $logger = App::getLogger();
            if ($logger instanceof Logger) {
                $logger->getConfig()->flush(true);
            }
        });
    }

}