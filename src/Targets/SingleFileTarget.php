<?php

declare(strict_types=1);

namespace Rabbit\Log\Targets;

use Rabbit\Base\App;
use Rabbit\Base\Contract\InitInterface;
use Rabbit\Base\Core\Exception;
use Rabbit\Base\Helper\FileHelper;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\StringHelper;

class SingleFileTarget extends AbstractTarget implements InitInterface
{
    private ?string $logFile = null;
    private bool $enableRotation = true;
    private int $maxFileSize = 10240; // in KB
    private int $maxLogFiles = 5;
    private ?int $fileMode = null;
    private int $dirMode = 0775;
    private $fp = null;

    public function __destruct()
    {
        if (is_resource($this->fp)) {
            @fclose($this->fp);
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

    public function export(array $msg): void
    {
        if ($this->fp === null) {
            if (false === $this->fp = @fopen($this->logFile, 'a+')) {
                throw new \InvalidArgumentException("Unable to append to log file: {$this->logFile}");
            }
        }
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
            return;
        }
        ArrayHelper::remove($msg, '%c');
        $msg = implode($this->split, $msg);
        if ($this->batch > 0 && getCid() !== -1) {
            $this->loop();
            $this->channel->push($msg);
        } else {
            $this->flush($msg);
        }
    }

    protected function flush(array|string &$logs): void
    {
        if ($this->fileMode !== null) {
            @chmod($this->logFile, $this->fileMode);
        }
        if ($this->enableRotation) {
            clearstatcache();
        }
        if ($this->enableRotation && @filesize($this->logFile) > $this->maxFileSize * 1024) {
            $this->rotateFiles($this->logFile);
        }
        @flock($this->fp, LOCK_EX);
        if (is_array($logs)) {
            foreach ($logs as &$msg) {
                @fwrite($this->fp, ($this->oneLine ? str_replace(PHP_EOL, ' ', $msg) : $msg) . PHP_EOL);
            }
        } else {
            @fwrite($this->fp, ($this->oneLine ? str_replace(PHP_EOL, ' ', $logs) : $logs) . PHP_EOL);
        }
        @flock($this->fp, LOCK_UN);
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
                    $this->clearLogFile();
                }
            }
        }
    }

    private function clearLogFile(): void
    {
        if ($filePointer = $this->fp ?? false) {
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
