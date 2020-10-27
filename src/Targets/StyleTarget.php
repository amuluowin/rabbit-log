<?php

declare(strict_types=1);

namespace Rabbit\Log\Targets;

use Psr\Log\LogLevel;
use DI\NotFoundException;
use DI\DependencyException;
use Rabbit\Log\ConsoleColor;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\StringHelper;

/**
 * Class StyleTarget
 * @package Rabbit\Log\Targets
 */
class StyleTarget extends AbstractTarget
{
    const COLOR_RANDOM = 'random';
    const COLOR_DEFAULT = 'default';
    const COLOR_LEVEL = 'level';
    /** @var ConsoleColor */
    private ConsoleColor $color;
    /** @var array */
    private array $colorTemplate = [
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
    private string $default = 'none';
    /** @var string */
    private string $splitColor = 'cyan';

    /**
     * StyleTarget constructor.
     * @param string $split
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct(string $split = ' | ')
    {
        parent::__construct($split);
        $this->color = create(ConsoleColor::class);
    }

    /**
     * @param array $messages
     */
    public function export(array $messages): void
    {
        foreach ($messages as $message) {
            foreach ($message as $msg) {
                if (is_string($msg)) {
                    switch (ini_get('seaslog.appender')) {
                        case '2':
                        case '3':
                            $msg = trim(substr($msg, StringHelper::str_n_pos($msg, ' ', 6)));
                            break;
                    }
                    $msg = explode($this->split, trim($msg));
                    $ranColor = $this->default;
                } else {
                    $ranColor = ArrayHelper::remove($msg, '%c');
                }
                if (!empty($this->levelList) && !in_array(strtolower($msg[$this->levelIndex]), $this->levelList)) {
                    continue;
                }
                if (empty($ranColor)) {
                    $ranColor = $this->default;
                } elseif (is_array($ranColor) && count($ranColor) === 2) {
                    $ranColor = $ranColor[0];
                } else {
                    $ranColor = $this->default;
                }
                $context = [];
                foreach ($msg as $index => $m) {
                    if (isset($this->colorTemplate[$index])) {
                        $color = $this->colorTemplate[$index];
                        $m = is_string($m) ? trim($m) : (string)$m;
                        $level = trim($msg[$this->levelIndex]);
                        switch ($color) {
                            case self::COLOR_LEVEL:
                                $context[] = $this->color->apply($this->getLevelColor($level), $m);
                                break;
                            case self::COLOR_DEFAULT:
                                $context[] = $this->color->apply($this->default, $m);
                                break;
                            case self::COLOR_RANDOM:
                                $context[] = $this->color->apply($ranColor, $m);
                                break;
                            default:
                                $context[] = $this->color->apply($color, $m);
                        }
                    } else {
                        $context[] = $this->color->apply($this->default, $m);
                    }
                }
                if (!empty($context)) {
                    echo implode(' ' . $this->color->apply($this->splitColor, '|') . ' ', $context) . PHP_EOL;
                }
            }
        }
    }

    /**
     * @param string $level
     * @return string
     */
    private function getLevelColor(string $level): string
    {
        switch (strtolower($level)) {
            case LogLevel::INFO:
                return "green";
            case LogLevel::DEBUG:
                return 'dark_gray';
            case LogLevel::ERROR:
                return "red";
            case LogLevel::WARNING:
                return 'yellow';
            default:
                return 'light_red';
        }
    }
}
