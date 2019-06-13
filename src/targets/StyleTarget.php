<?php


namespace rabbit\log\targets;

use rabbit\log\ConsoleColor;

/**
 * Class StyleTarget
 * @package rabbit\log\targets
 */
class StyleTarget implements TargetInterface
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
    /** @var array */
    private $possibleStyles = [];

    /**
     * StyleTarget constructor.
     */
    public function __construct(ConsoleColor $color)
    {
        $this->color = $color;
        $this->possibleStyles = $color->getPossibleStyles();
    }

    /**
     * @param array $messages
     * @param bool $flush
     */
    public function export(array $messages, bool $flush = true): void
    {
        $randoms = [];
        foreach ($this->colorTemplate as $index => $color) {
            if ($color === self::COLOR_RANDOM) {
                $randoms[$index] = $this->possibleStyles[rand(0, count($this->possibleStyles) - 1)];
            }
        }
        foreach ($messages as $message) {
            foreach ($message as $msg) {
                $context = [];
                $arrMsg = explode(' | ', $msg);
                foreach ($arrMsg as $index => $m) {
                    if (isset($this->colorTemplate[$index])) {
                        $color = $this->colorTemplate[$index];
                        switch ($color) {
                            case self::COLOR_LEVEL:
                                $context[] = $this->color->apply($this->getLevelColor($arrMsg[1]), $m);
                                break;
                            case self::COLOR_DEFAULT:
                                $context[] = $m;
                                break;
                            case self::COLOR_RANDOM:
                                $context[] = $this->color->apply($randoms[$index], $m);
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