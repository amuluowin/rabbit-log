<?php

declare(strict_types=1);

namespace Rabbit\Log\Targets;

use Rabbit\Base\Core\Channel;

abstract class AbstractTarget
{
    protected array $levelList = [];
    protected int $levelIndex = 1;
    protected Channel $channel;
    protected int $batch = 0;
    protected int $waitTime = 1;
    protected bool $isRunning = false;

    public function __construct(public string $split = ' | ', public bool $oneLine = false)
    {
    }

    public function getLogs($channel = null): array
    {
        $channel = $channel ?? $this->channel;
        $logs = [];
        for ($i = 0; $i < $this->batch; $i++) {
            if (false === $log = $channel->pop($this->waitTime)) {
                break;
            }
            $logs[] = $log;
        }
        return $logs;
    }

    abstract public function export(array $msg): void;

    protected function loop(): void
    {
        if ($this->isRunning) {
            return;
        }
        $this->channel = new Channel();
        $this->isRunning = true;
        loop(function (): void {
            $logs = $this->getLogs();
            if (empty($logs)) {
                return;
            }
            $this->flush($logs);
        });
    }

    abstract protected function flush(array|string &$logs): void;
}
