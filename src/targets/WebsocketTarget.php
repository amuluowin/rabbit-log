<?php


namespace rabbit\log\targets;

use rabbit\App;
use rabbit\helper\ArrayHelper;
use rabbit\helper\JsonHelper;

/**
 * Class WebsocketTarget
 * @package rabbit\log\targets
 */
class WebsocketTarget extends AbstractTarget
{
    /**
     * @param array $messages
     * @param bool $flush
     * @throws \Exception
     */
    public function export(array $messages, bool $flush = true): void
    {
        $fdList = getClientList();
        $server = App::getServer();
        foreach ($messages as $message) {
            foreach ($message as $msg) {
                ArrayHelper::remove($msg, '%c');
                $msg = JsonHelper::encode($msg);
                foreach ($fdList as $fd) {
                    rgo(function () use ($server, $fd, $msg) {
                        $server->isEstablished($fd) && $server->push($fd, $msg);
                    });
                }
            }
        }
    }

}