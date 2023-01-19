<?php

declare(strict_types=1);

namespace Rabbit\Log\Targets;

use Psr\Log\LogLevel;
use Rabbit\Log\ConsoleColor;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\StringHelper;

class StyleTarget extends AbstractTarget
{
    const COLOR_RANDOM = 'random';
    const COLOR_DEFAULT = 'default';
    const COLOR_LEVEL = 'level';
    private ConsoleColor $color;
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
    private string $splitColor = 'cyan';

    protected array $colorMap = [
        LogLevel::INFO => 'green',
        LogLevel::DEBUG => 'dark_gray',
        LogLevel::WARNING => 'yellow',
        LogLevel::ERROR => 'red'
    ];

    public function __construct(string $split = ' | ', public bool $useColor = true, public bool $oneLine = false)
    {
        parent::__construct($split);
        $this->color = create(ConsoleColor::class);
    }

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
                    $m = is_string($m) ? trim($m) : (string)$m;
                    if (isset($this->colorTemplate[$index]) && $this->useColor) {
                        $color = $this->colorTemplate[$index];
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
                    $str = implode(' ' . ($this->useColor ? $this->color->apply($this->splitColor, '|') : '|') . ' ', $context);
                    if ($this->oneLine) {
                        $str = str_replace(PHP_EOL, ' ', $str);
                    }
                    fwrite(STDOUT,  $str . PHP_EOL);
                }
            }
        }
    }

    private function getLevelColor(string $level): string
    {
        return $this->colorMap[strtolower($level)] ?? 'light_red';
    }
}
