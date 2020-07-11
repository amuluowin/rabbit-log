<?php
declare(strict_types=1);

namespace Rabbit\Log\Targets;

use Co\Channel;
use Rabbit\Base\App;
use Rabbit\Base\Core\Exception;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\FileHelper;
use Rabbit\Base\Helper\StringHelper;

/**
 * Class FileTarget
 * @package rabbit\log\targets
 */
class FileTarget extends AbstractTarget
{
    private ?string $logFile = null;
    private bool $enableRotation = true;
    private int $maxFileSize = 10240; // in KB
    private int $maxLogFiles = 5;
    private ?int $fileMode = null;
    private int $dirMode = 0775;
    private array $poolList = [];

    public function __destruct()
    {
        foreach ($this->poolList as $file => [$channel, $fp, $lock]) {
            if (is_resource($fp)) {
                @fclose($fp);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();
        if ($this->logFile === null) {
            $this->logFile = App::getAlias('@runtime') . '/logs/app.log';
        } else {
            $this->logFile = strpos($this->logFile, '@') !== 0 ? App::getAlias($this->logFile) : $this->logFile;
        }
        if ($this->maxLogFiles < 1) {
            $this->maxLogFiles = 1;
        }
        if ($this->maxFileSize < 1) {
            $this->maxFileSize = 1;
        }
        $logPath = dirname($this->logFile);
        FileHelper::createDirectory($logPath, $this->dirMode, true);
    }

    /**
     * @param array $messages
     */
    public function export(array $messages): void
    {
        $fileInfo = pathinfo($this->logFile);
        foreach ($messages as $module => $message) {
            if (!empty(pathinfo($module, PATHINFO_EXTENSION))) {
                $file = $module;
            } else {
                $fileInfo['filename'] = $module;
                $file = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.' . (isset($fileInfo['extension']) ? $fileInfo['extension'] : 'log');
            }
            if (!isset($this->poolList[$file])) {
                $channel = new Channel();
                $this->poolList[$file] = $channel;
                if (($fp = @fopen($file, 'a+')) === false) {
                    throw new \InvalidArgumentException("Unable to append to log file: {$file}");
                }
                loop(function () use ($file, $channel, $fp) {
                    $logs = $this->getLogs($channel);
                    if (empty($logs)) {
                        return;
                    }
                    if ($this->fileMode !== null) {
                        @chmod($file, $this->fileMode);
                    }
                    if ($this->enableRotation) {
                        // clear stat cache to ensure getting the real current file size and not a cached one
                        // this may result in rotating twice when cached file size is used on subsequent calls
                        clearstatcache();
                    }
                    if ($this->enableRotation && @filesize($file) > $this->maxFileSize * 1024) {
                        $this->rotateFiles($file);
                    }
                    @flock($fp, LOCK_EX);
                    @fwrite($fp, implode("", $logs));
                    @flock($fp, LOCK_UN);
                });
            } else {
                $channel = $this->poolList[$file];
            }
            foreach ($message as $msg) {
                if (is_string($msg)) {
                    switch (ini_get('seaslog.appender')) {
                        case '2':
                        case '3':
                            $msg = trim(substr($msg, StringHelper::str_n_pos($msg, ' ', 6)));
                            break;
                    }
                    $msg = explode($this->split, trim($msg));
                }
                if (!empty($this->levelList) && !in_array(strtolower($msg[$this->levelIndex]), $this->levelList)) {
                    continue;
                }
                ArrayHelper::remove($msg, '%c');
                $msg = implode($this->split, $msg) . PHP_EOL;
                $channel->push($msg);
            }
        }
    }

    /**
     * @throws NotSupportedException
     */
    protected function write(): void
    {
        throw new NotSupportedException("FileTarget not support write func");
    }


    /**
     * @param string $file
     */
    protected function rotateFiles(string $file)
    {
        $fileInfo = pathinfo($file);
        for ($i = $this->maxLogFiles; $i >= 0; --$i) {
            // $i == 0 is the original log file
            $rotateFile = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . ($i === 0 ? '' : '-f' . $i) . '.' . $fileInfo['extension'];
            if (is_file($rotateFile)) {
                // suppress errors because it's possible multiple processes enter into this section
                if ($i === $this->maxLogFiles) {
                    @unlink($rotateFile);
                    continue;
                }
                $newFile = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '-f' . ($i + 1) . '.' . $fileInfo['extension'];
                $this->rotateByCopy($rotateFile, $newFile);
                if ($i === 0) {
                    $this->clearLogFile($rotateFile);
                }
            }
        }
    }

    /***
     * Clear log file without closing any other process open handles
     * @param string $rotateFile
     */
    private function clearLogFile($rotateFile)
    {
        if ($filePointer = @fopen($rotateFile, 'a')) {
            @flock($filePointer, LOCK_EX);
            @ftruncate($filePointer, 0);
            @flock($filePointer, LOCK_UN);
            @fclose($filePointer);
        }
    }

    /***
     * Copy rotated file into new file
     * @param string $rotateFile
     * @param string $newFile
     */
    private function rotateByCopy($rotateFile, $newFile)
    {
        @copy($rotateFile, $newFile);
        if ($this->fileMode !== null) {
            @chmod($newFile, $this->fileMode);
        }
    }
}
