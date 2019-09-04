<?php


namespace rabbit\log\targets;

use Psr\Log\LogLevel;
use rabbit\App;
use rabbit\helper\ArrayHelper;
use rabbit\helper\StringHelper;
use rabbit\log\HtmlColor;
use rabbit\wsserver\Server;

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
    /** @var string */
    private $route = '/logs';

    /**
     * @param array $messages
     * @param bool $flush
     * @throws \Exception
     */
    public function export(array $messages, bool $flush = true): void
    {
        $server = App::getServer();
        if (!$server) {
            return;
        }
        $table = $server->getTable();
        /** @var Server $swooleServer */
        $swooleServer = $server->getSwooleServer();
        foreach ($swooleServer->connections as $fd) {
            if ($table->exist($fd) && $table->get($fd,
                    'path') === $this->route && $swooleServer->isEstablished($fd)) {
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
                            $ranColor = $ranColor[1];
                        } else {
                            $ranColor = $this->default;
                        }
                        foreach ($msg as $index => $m) {
                            $msg[$index] = trim($m);
                            if (isset($this->colorTemplate[$index])) {
                                $color = $this->colorTemplate[$index];
                                $level = trim($msg[$this->levelIndex]);
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
                        $msg = json_encode([$msg, $colors], JSON_UNESCAPED_UNICODE);
                        rgo(function () use ($swooleServer, $fd, $msg) {
                            $swooleServer->push($fd, $msg);
                        });
                    }
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
                return "Green";
            case LogLevel::DEBUG:
                return 'DarkGray';
            case LogLevel::ERROR:
                return "Red";
            case LogLevel::WARNING:
                return 'Yellow';
            default:
                return 'DarkRed';
        }
    }

}