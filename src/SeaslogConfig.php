<?php
declare(strict_types=1);

namespace Rabbit\Log;

use Rabbit\Base\Contract\InitInterface;
use Throwable;
use Seaslog;

/**
 * Class SeaslogConfig
 * @package Rabbit\Log
 */
class SeaslogConfig extends AbstractConfig implements InitInterface
{
    /** @var string */
    private string $appName = 'Rabbit';
    /** @var Seaslog */
    private Seaslog $logger;

    /**
     * SeaslogConfig constructor.
     * @param array $target
     * @throws Throwable
     */
    public function __construct(array $target)
    {
        parent::__construct($target);
        $this->appName = (string)getDI('appName', false, 'Rabbit');
        $this->logger = new Seaslog();
    }

    public function init()
    {
        ini_set('seaslog.recall_depth', $this->recall_depth);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $template = $this->getTemplate();
        $module = isset($context['module']) ? $context['module'] : null;
        if ($module !== null) {
            $this->logger->setLogger($this->appName . '_' . $module);
        }
        isset($template['%Q']) && $this->logger->setRequestID($template['%Q']);
        foreach (array_filter([
            SEASLOG_REQUEST_VARIABLE_DOMAIN_PORT => isset($template['%D']) ? $template['%D'] : null,
            SEASLOG_REQUEST_VARIABLE_REQUEST_URI => isset($template['%R']) ? $template['%R'] : null,
            SEASLOG_REQUEST_VARIABLE_REQUEST_METHOD => isset($template['%m']) ? $template['%m'] : null,
            SEASLOG_REQUEST_VARIABLE_CLIENT_IP => isset($template['%I']) ? $template['%I'] : null
        ]) as $key => $value) {
            $this->logger->setRequestVariable($key, $value);
        }
        $this->logger->$level($message);
        $buffer = $this->logger->getBuffer();
        $this->flush($buffer);
    }

    /**
     * @param array $buffer
     */
    public function flush(array $buffer): void
    {
        $this->logger->flushBuffer(0);
        foreach ($this->targetList as $index => $target) {
            rgo(function () use ($target, &$buffer) {
                $target->export($buffer);
            });
        }
    }
}
