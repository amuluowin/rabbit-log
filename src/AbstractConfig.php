<?php

declare(strict_types=1);

namespace Rabbit\Log;

/**
 * Class AbstractConfig
 * @package Rabbit\Log
 */
abstract class AbstractConfig
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
