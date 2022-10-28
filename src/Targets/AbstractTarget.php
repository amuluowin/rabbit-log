<?php

declare(strict_types=1);

namespace Rabbit\Log\Targets;

use Rabbit\Base\Core\Channel;

/**
 * Class AbstractTarget
 * @package rabbit\log\targets
 */
abstract class AbstractTarget
{
    /** @var string */
    protected string $split = ' | ';
    /** @var array */
    protected array $levelList = [];
    /** @var int */
    protected int $levelIndex = 1;
    protected Channel $channel;
    /** @var int */
    protected int $batch = 100;
    /** @var float */
    protected float $waitTime = 1;

    /**
     * AbstractTarget constructor.
     * @param string $split
     */
    public function __construct(string $split = ' | ')
    {
        $this->split = $split;
    }

    /**
     * @param Channel|null $channel
     * @return array
     */
    public function getLogs($channel = null): array
    {
        $channel = $channel ?? $this->channel;
        $logs = [];
        for ($i = 0; $i < $this->batch; $i++) {
            $log = $channel->pop((int)$this->waitTime);
            if ($log === false) {
                break;
            }
            $logs[] = $log;
        }
        return $logs;
    }


    /**
     * @param array $messages
     */
    abstract public function export(array $messages): void;

    protected function loop(): void
    {
        $this->channel = new Channel();
    }
}
