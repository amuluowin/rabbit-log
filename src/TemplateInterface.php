<?php
declare(strict_types=1);

namespace Rabbit\Log;

/**
 * Interface TemplateInterface
 * @package Rabbit\Log
 */
interface TemplateInterface
{
    /**
     * @return array
     */
    public function handle(): array;
}