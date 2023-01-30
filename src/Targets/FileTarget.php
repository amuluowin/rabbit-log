<?php

declare(strict_types=1);

namespace Rabbit\Log\Targets;

use Rabbit\Base\App;
use Rabbit\Base\Contract\InitInterface;
use Rabbit\Base\Core\Exception;
use Rabbit\Base\Helper\FileHelper;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\StringHelper;

class FileTarget extends AbstractTarget implements InitInterface
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
        foreach ($this->poolList as $fp) {
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
        $this->loop();
        $fileInfo = pathinfo($this->logFile);
        foreach ($messages as $module => $message) {
            if (!empty(pathinfo($module, PATHINFO_EXTENSION))) {
                $file = $module;
            } else {
                $fileInfo['filename'] = $module;
                $file = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.' . (isset($fileInfo['extension']) ? $fileInfo['extension'] : 'log');
            }
            if (!isset($this->poolList[$file])) {
                $this->poolList[$file] = true;
                if (($this->poolList[$file] = @fopen($file, 'a+')) === false) {
                    throw new \InvalidArgumentException("Unable to append to log file: {$file}");
                }
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
                $msg = implode($this->split, $msg);
                $this->channel->push(['file' => $file, 'msg' => $msg]);
            }
        }
    }

    protected function flush(array|string &$logs): void
    {
        wgeach($logs, function (string $file, array $msg): void {
            if ($this->fileMode !== null) {
                @chmod($file, $this->fileMode);
            }
            if ($this->enableRotation) {
                clearstatcache();
            }
            if ($this->enableRotation && @filesize($file) > $this->maxFileSize * 1024) {
                $this->rotateFiles($file);
            }
            $fp = $this->poolList[$file];
            $msg = array_column($msg, 'msg');
            @flock($fp, LOCK_EX);
            @fwrite($fp, implode(PHP_EOL, $msg));
            @flock($fp, LOCK_UN);
        });
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
                    $this->clearLogFile($file);
                }
            }
        }
    }

    private function clearLogFile(string $file): void
    {
        if ($filePointer = $this->poolList[$file] ?? false) {
            @flock($filePointer, LOCK_EX);
            @ftruncate($filePointer, 0);
            @flock($filePointer, LOCK_UN);
        }
    }

    private function rotateByCopy(string $rotateFile, string $newFile): void
    {
        @copy($rotateFile, $newFile);
        if ($this->fileMode !== null) {
            @chmod($newFile, $this->fileMode);
        }
    }
}
