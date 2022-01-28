<?php

declare(strict_types=1);

namespace Rabbit\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Rabbit\Base\Contract\InitInterface;

class Logger implements LoggerInterface, InitInterface
{
    protected array $level = [];

    const CONTEXT_KEY = 'logger.default';

    public function __construct(private AbstractConfig $config)
    {
    }

    public function init(): void
    {
        if ($this->config instanceof InitInterface) {
            $this->config->init();
        }
    }

    public function getConfig(): AbstractConfig
    {
        return $this->config;
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        if (empty($this->level) || in_array($level, $this->level)) {
            if (is_string($message)) {
                $this->config->log($level, $message, $context);
            } elseif (is_array($message)) {
                foreach ($message as $m) {
                    $this->config->log($level, (string)$m, $context);
                }
            }
        }
    }
}
