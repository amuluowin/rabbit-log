<?php

declare(strict_types=1);

namespace Rabbit\Log\Targets;

use Rabbit\Base\Contract\InitInterface;

/**
 * Class AbstractTarget
 * @package rabbit\log\targets
 */
abstract class AbstractTarget implements InitInterface
{
    /** @var string */
    protected string $split = ' | ';
    /** @var array */
    protected array $levelList = [];
    /** @var int */
    protected int $levelIndex = 1;
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

    public function init(): void
    {
    }


    /**
     * @param array $messages
     */
    abstract public function export(array $messages): void;
}
