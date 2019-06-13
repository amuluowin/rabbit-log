<?php


namespace rabbit\log\targets;

use rabbit\log\ConsoleColor;

/**
 * Class StyleTarget
 * @package rabbit\log\targets
 */
class StyleTarget implements TargetInterface
{
    /** @var ConsoleColor */
    private $color;
    /** @var array */
    private $colorTemplate = [
        'magenta',
        'level',
        'level',
        'dark_gray',
        'dark_gray',
        'dark_gray',
        'level',
        'dark_gray',
        'level'
    ];

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
                $context = [];
                $arrMsg = explode(' | ', $msg);
                foreach ($arrMsg as $index => $m) {
                    if (isset($this->colorTemplate[$index])) {
                        $color = $this->colorTemplate[$index];
                        switch ($color) {
                            case 'level':
                                $context[] = $this->color->apply($this->getLevelColor($arrMsg[1]), $m);
                                break;
                            case 'default':
                                $context[] = $m;
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