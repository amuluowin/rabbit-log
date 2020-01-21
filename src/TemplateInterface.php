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
     * @return array
     */
    public function handle(): array ;
}