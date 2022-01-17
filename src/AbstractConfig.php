<?php

declare(strict_types=1);

namespace Rabbit\Log;

use Rabbit\Log\Targets\AbstractTarget;
use Rabbit\Base\Contract\InitInterface;

/**
 * Class AbstractConfig
 * @package Rabbit\Log
 */
abstract class AbstractConfig implements InitInterface
{
    protected int $recall_depth = 0;
    protected ?TemplateInterface $userTemplate = null;

    /**
     * AbstractConfig constructor.
     * @param array $target
     */
    public function __construct(protected array $targetList)
    {
    }

    public function init(): void
    {
        foreach ($this->targetList as $target) {
            $target->init();
        }
    }

    /**
     * @return array
     */
    protected function getTemplate(): array
    {
        if ($this->userTemplate instanceof TemplateInterface) {
            $template = $this->userTemplate->handle();
            $template = $template ?? [];
        } else {
            $template = [];
        }
        return $template;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    abstract public function log(string $level, string $message, array $context = []): void;

    /**
     * @param array $buffer
     */
    abstract public function flush(array $buffer = []): void;
}
