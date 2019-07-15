<?php


namespace rabbit\log;

use rabbit\core\Context;
use rabbit\exception\InvalidConfigException;
use rabbit\helper\ArrayHelper;
use rabbit\helper\CoroHelper;

/**
 * Class LoggerConfig
 * @package rabbit\log
 */
class LoggerConfig extends AbstractConfig
{
    /** @var string */
    protected $datetime_format = "Y-m-d H:i:s";
    /** @var int */
    protected $recall_depth = 0;
    /** @var array */
    protected $template;
    /** @var string */
    protected $split = ' | ';
    /** @var int */
    protected $isMicrotime = 3;
    /** @var array */
    private static $supportTemplate = [
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
     * @param array $template
     * @throws InvalidConfigException
     */
    public function __construct(
        array $target,
        array $template = ['%T', '%L', '%R', '%m', '%I', '%Q', '%F', '%U', '%M']
    ) {
        parent::__construct($target);
        foreach ($template as $tmp) {
            if (!in_array($tmp, self::$supportTemplate)) {
                throw new InvalidConfigException("$tmp not supported!");
            }
        }
        $this->template = $template;
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
     * @throws \Exception
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $msg = [];
        $template = Context::get(Logger::CONTEXT_KEY);
        $template = $template ?? [];
        foreach ($this->template as $tmp) {
            switch ($tmp) {
                case '%W':
                    $msg[] = ArrayHelper::getValue($template, $tmp, -1);
                    break;
                case '%L':
                    $msg[] = strtoupper($level);
                    break;
                case '%M':
                    $msg[] = str_replace($this->split, ' ', $message);
                    break;
                case '%T':
                case '%t':
                    if ($this->isMicrotime > 0) {
                        $micsec = $this->isMicrotime > 3 ? 3 : $this->isMicrotime;
                        $mtimestamp = sprintf("%.{$micsec}f", microtime(true)); // 带毫秒的时间戳
                        $timestamp = floor($mtimestamp); // 时间戳
                        $milliseconds = round(($mtimestamp - $timestamp) * 1000); // 毫秒
                    } else {
                        $timestamp = time();
                        $milliseconds = 0;
                    }
                    if ($tmp === '%T') {
                        $msg[] = date($this->datetime_format, $timestamp) . '.' . $milliseconds;
                    } else {
                        $msg[] = date($this->datetime_format, $timestamp);
                    }
                    break;
                case '%Q':
                    $msg[] = ArrayHelper::getValue($template, $tmp, uniqid());
                    break;
                case '%H':
                    $msg[] = ArrayHelper::getValue($template, $tmp,
                        isset($_SERVER['HOSTNAME']) ? $_SERVER['HOSTNAME'] : 'local');
                    break;
                case '%P':
                    $msg[] = ArrayHelper::getValue($template, $tmp, getmypid());
                    break;
                case '%D':
                    $msg[] = ArrayHelper::getValue($template, $tmp, 'cli');
                    break;
                case '%R':
                    $msg[] = ArrayHelper::getValue($template, $tmp,
                        isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/');
                    break;
                case '%m':
                    $msg[] = strtoupper(ArrayHelper::getValue($template, $tmp, 'unknow'));
                    break;
                case '%I':
                    $msg[] = ArrayHelper::getValue($template, $tmp, '127.0.0.1');
                    break;
                case '%F':
                case '%C':
                    $trace = \Co::getBackTrace(CoroHelper::getId(), DEBUG_BACKTRACE_IGNORE_ARGS,
                        $this->recall_depth + 2);
                    if ($tmp === '%F') {
                        $trace = $trace[$this->recall_depth];
                        $msg[] = $trace['file'] . ':' . $trace['line'];
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
        $color && $msg['%c'] = $color;
        $appName = getDI('appName', false, 'rabbit');
        $key = $appName . '_' . ArrayHelper::getValue($context, 'module', 'system');
        $this->buffer[$key][] = $msg;
        $this->flush();
    }
}