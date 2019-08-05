<?php


namespace rabbit\log\targets;

use rabbit\App;
use rabbit\helper\ArrayHelper;
use rabbit\helper\JsonHelper;
use rabbit\helper\StringHelper;
use rabbit\log\HtmlColor;

/**
 * Class WebsocketTarget
 * @package rabbit\log\targets
 */
class WebsocketTarget extends AbstractTarget
{
    const COLOR_RANDOM = 'random';
    const COLOR_LEVEL = 'level';
    const COLOR_DEFAULT = 'default';

    /** @var array */
    private $colorTemplate = [
        'Magenta',
        self::COLOR_LEVEL,
        self::COLOR_LEVEL,
        'DarkGray',
        'DarkGray',
        self::COLOR_RANDOM,
        self::COLOR_LEVEL,
        'DarkGray',
        self::COLOR_LEVEL
    ];
    /** @var string */
    private $default = 'LightGray';

    /**
     * @param array $messages
     * @param bool $flush
     * @throws \Exception
     */
    public function export(array $messages, bool $flush = true): void
    {
        $fdList = getClientList();
        $server = App::getServer();
        foreach ($fdList as $fd) {
            foreach ($messages as $message) {
                foreach ($message as $msg) {
                    if (is_string($msg)) {
                        switch (ini_get('seaslog.appender')) {
                            case '2':
                            case '3':
                                $msg = trim(substr($msg, StringHelper::str_n_pos($msg, ' ', 6)));
                                break;
                        }
                        $msg = explode($this->split, $msg);
                        $ranColor = $this->default;
                    } else {
                        $ranColor = ArrayHelper::remove($msg, '%c');
                    }
                    if (empty($ranColor)) {
                        $ranColor = $this->default;
                    } elseif (is_array($ranColor) && count($ranColor) === 2) {
                        $ranColor = $ranColor[1];
                    } else {
                        $ranColor = $this->default;
                    }
                    foreach ($msg as $index => $m) {
                        $msg[$index] = trim($m);
                        if (isset($this->colorTemplate[$index])) {
                            $color = $this->colorTemplate[$index];
                            $level = trim($msg[1]);
                            switch ($color) {
                                case self::COLOR_LEVEL:
                                    $colors[] = HtmlColor::getColor($this->getLevelColor($level));
                                    break;
                                case self::COLOR_RANDOM:
                                    $colors[] = HtmlColor::getColor($ranColor);
                                    break;
                                case self::COLOR_DEFAULT:
                                    $colors[] = $this->default;
                                    break;
                                default:
                                    $colors[] = HtmlColor::getColor($color);
                            }
                        } else {
                            $colors[] = $this->default;
                        }
                    }
                    $msg = JsonHelper::encode([$msg, $colors]);
                    rgo(function () use ($server, $fd, $msg) {
                        $server->isEstablished($fd) && $server->push($fd, $msg);
                    });
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
        switch ($level) {
            case 'INFO':
                return "Green";
            case 'DEBUG':
                return 'DarkGray';
            case 'ERROR':
                return "Red";
            case 'WARNING':
                return 'Yellow';
            default:
                return 'DarkRed';
        }
    }

}