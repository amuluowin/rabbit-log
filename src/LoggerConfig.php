<?php

declare(strict_types=1);

namespace Rabbit\Log;

use Throwable;
use Rabbit\Base\App;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\ExceptionHelper;
use Rabbit\Base\Exception\InvalidConfigException;

/**
 * Class LoggerConfig
 * @package Rabbit\Log
 */
class LoggerConfig extends AbstractConfig
{
    /** @var string */
    protected string $datetime_format = "Y-m-d H:i:s";
    /** @var array */
    protected ?array $template = null;
    /** @var string */
    protected string $split = ' | ';
    /** @var int */
    protected int $isMicrotime = 3;
    /** @var string */
    private string $appName = 'Rabbit';
    /** @var bool */
    protected bool $useBasename = false;
    /** @var array */
    private static array $supportTemplate = [
        '%W',
        '%L',
        '%M',
        '%T',
        '%t',
        '%Q',
        '%H',
        '%P',
        '%D',
        '%R',
        '%m',
        '%I',
        '%F',
        '%U',
        '%u',
        '%C'
    ];

    /**
     * LoggerConfig constructor.
     * @param array $target
     * @param float $tick
     * @param array $template
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function __construct(
        array $target,
        float $tick = 0,
        array $template = ['%T', '%L', '%R', '%m', '%I', '%Q', '%F', '%U', '%M']
    ) {
        parent::__construct($target);
        foreach ($template as $tmp) {
            if (!in_array($tmp, self::$supportTemplate)) {
                throw new InvalidConfigException("$tmp not supported!");
            }
        }
        $this->template = $template;
        $this->appName = (string)getDI('appName', false, 'Rabbit');
    }

    /**
     * @return string
     */
    public function getSplit(): string
    {
        return $this->split;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     * @throws Throwable
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $template = $this->getTemplate();
        $msg = [];
        foreach ($this->template as $tmp) {
            switch ($tmp) {
                case '%W':
                    $msg[] = ArrayHelper::getValue($template, $tmp, -1);
                    break;
                case '%L':
                    $msg[] = $level;
                    break;
                case '%M':
                    $msg[] = str_replace($this->split, ' ', $message);
                    break;
                case '%T':
                    $micsec = in_array($this->isMicrotime, [3, 6]) ? $this->isMicrotime : 3;
                    $mtimestamp = sprintf("%.{$micsec}f", microtime(true));
                    [$timestamp, $milliseconds] = explode('.', $mtimestamp);
                    $msg[] = date($this->datetime_format, (int)$timestamp) . '.' . $milliseconds;
                    break;
                case '%t':
                    $timestamp = time();
                    $msg[] = date($this->datetime_format, $timestamp);
                    break;
                case '%Q':
                    $msg[] = ArrayHelper::getValue($template, $tmp, uniqid());
                    break;
                case '%H':
                    $msg[] = ArrayHelper::getValue(
                        $template,
                        $tmp,
                        isset($_SERVER['HOSTNAME']) ? $_SERVER['HOSTNAME'] : 'local'
                    );
                    break;
                case '%P':
                    $msg[] = ArrayHelper::getValue($template, $tmp, getmypid());
                    break;
                case '%D':
                    $msg[] = ArrayHelper::getValue($template, $tmp, 'cli');
                    break;
                case '%R':
                    $msg[] = ArrayHelper::getValue(
                        $template,
                        $tmp,
                        isset($_SERVER['SCRIPT_FILENAME']) ? str_replace(App::getAlias('@root', false) . '/', '', $_SERVER['SCRIPT_FILENAME']) : '/'
                    );
                    break;
                case '%m':
                    $msg[] = strtolower(ArrayHelper::getValue($template, $tmp, ArrayHelper::getValue($_SERVER, 'SHELL', 'unknow')));
                    break;
                case '%I':
                    $msg[] = ArrayHelper::getValue($template, $tmp, current(swoole_get_local_ip()));
                    break;
                case '%F':
                case '%C':
                    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->recall_depth);
                    if ($tmp === '%F') {
                        $trace = $trace[$this->recall_depth] ?? end($trace);
                        $msg[] = $this->useBasename ? basename($trace['file']) . ':' . $trace['line'] : (($path = App::getAlias('@root', false)) ? str_replace($path . '/', '', $trace['file']) :
                            $trace['file']) . ':' . $trace['line'];
                    } else {
                        $trace = $trace[$this->recall_depth + 1];
                        $msg[] = $trace['class'] . $trace['type'] . $trace['function'];
                    }
                    break;
                case '%U':
                    $msg[] = memory_get_usage();
                    break;
                case '%u':
                    $msg[] = memory_get_peak_usage();
                    break;
            }
        }
        $color = ArrayHelper::getValue($template, '%c');
        $color !== null && $msg['%c'] = $color;
        $key = $this->appName . '_' . ArrayHelper::getValue($context, 'module', 'system');
        $buffer[$key][] = $msg;
        $this->flush($buffer);
    }

    /**
     * @param array $buffer
     * @throws Throwable
     */
    public function flush(array $buffer = []): void
    {
        if (!empty($buffer)) {
            foreach ($this->targetList as $target) {
                rgo(function () use ($target, &$buffer) {
                    try {
                        $target->export($buffer);
                    } catch (Throwable $exception) {
                        print_r(ExceptionHelper::convertExceptionToArray($exception));
                    }
                });
            }
        }
    }
}
