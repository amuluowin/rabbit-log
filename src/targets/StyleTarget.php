<?php


namespace rabbit\log\targets;

use rabbit\helper\ArrayHelper;
use rabbit\log\ConsoleColor;

/**
 * Class StyleTarget
 * @package rabbit\log\targets
 */
class StyleTarget extends AbstractTarget
{
    const COLOR_RANDOM = 'random';
    const COLOR_DEFAULT = 'default';
    const COLOR_LEVEL = 'level';
    /** @var ConsoleColor */
    private $color;
    /** @var array */
    private $colorTemplate = [
        'magenta',
        self::COLOR_LEVEL,
        self::COLOR_LEVEL,
        'dark_gray',
        'dark_gray',
        self::COLOR_RANDOM,
        self::COLOR_LEVEL,
        'dark_gray',
        self::COLOR_LEVEL
    ];

    /** @var string */
    private $splitColor = 'cyan';

    /**
     * StyleTarget constructor.
     */
    public function __construct(ConsoleColor $color)
    {
        $this->color = $color;
    }

    /**
     * @param array $messages
     * @param bool $flush
     */
    public function export(array $messages, bool $flush = true): void
    {
        foreach ($messages as $message) {
            foreach ($message as $msg) {
                $ranColor = ArrayHelper::remove($msg, '%c');
                foreach ($msg as $index => $m) {
                    if (isset($this->colorTemplate[$index])) {
                        $color = $this->colorTemplate[$index];
                        switch ($color) {
                            case self::COLOR_LEVEL:
                                $context[] = $this->color->apply($this->getLevelColor($msg[1]), $m);
                                break;
                            case self::COLOR_DEFAULT:
                                $context[] = $m;
                                break;
                            case self::COLOR_RANDOM:
                                $context[] = $this->color->apply($ranColor, $m);
                                break;
                            default:
                                $context[] = $this->color->apply($color, $m);
                        }
                    } else {
                        $context[] = $m;
                    }
                }
                echo implode(' ' . $this->color->apply($this->splitColor, '|') . ' ', $context) . PHP_EOL;
            }
        }
    }

    /**
     * @param string $level
     * @return string
     */
    private function getLevelColor(string $level): string
    {
        switch ($level) {
            case 'INFO':
                return "green";
            case 'DEBUG':
                return 'dark_gray';
            case 'ERROR':
                return "red";
            case 'WARNING':
                return 'yellow';
            default:
                return 'light_red';
        }
    }

}