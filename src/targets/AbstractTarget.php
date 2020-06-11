<?php


namespace rabbit\log\targets;

use Co\Channel;
use rabbit\contract\InitInterface;

/**
 * Class AbstractTarget
 * @package rabbit\log\targets
 */
abstract class AbstractTarget implements InitInterface
{
    /** @var string */
    protected $split = ' | ';
    /** @var array */
    protected $levelList = [];
    /** @var int */
    protected $levelIndex = 1;
    /** @var Channel */
    protected $channel;
    /** @var int */
    protected $batch = 100;
    /** @var float */
    protected $waitTime = 0.05;

    /**
     * AbstractTarget constructor.
     * @param string $split
     */
    public function __construct(string $split = ' | ')
    {
        $this->split = $split;
        $this->channel = new Channel();
    }

    public function init()
    {
        $this->write();
    }

    /**
     * @return array
     */
    public function getLogs(): array
    {
        $logs = [];
        for ($i = 0; $i < $this->batch; $i++) {
            $log = $this->channel->pop($this->waitTime);
            if ($log === false) {
                break;
            }
            $logs[] = $log;
        }
        return $logs;
    }


    /**
     * @param array $messages
     * @param bool $flush
     */
    abstract public function export(array $messages): void;

    abstract protected function write(): void;
}
