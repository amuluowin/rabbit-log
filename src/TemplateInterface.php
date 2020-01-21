<?php
declare(strict_types=1);

namespace rabbit\log;

/**
 * Interface TemplateInterface
 * @package rabbit\log
 */
interface TemplateInterface
{
    /**
     * @param Logger $logger
     */
    public function handle(Logger $logger): void;
}