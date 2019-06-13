<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 16:38
 */

namespace rabbit\log\targets;

use rabbit\App;
use rabbit\files\FileHelper;

/**
 * Class FileTarget
 * @package rabbit\log\targets
 */
class FileTarget implements TargetInterface
{
    /**
     * @var string
     */
    private $logFile;
    /**
     * @var bool
     */
    private $enableRotation = true;
    /**
     * @var int
     */
    private $maxFileSize = 1024; // in KB
    /**
     * @var int
     */
    private $maxLogFiles = 5;
    /**
     * @var int
     */
    private $fileMode;
    /**
     * @var int
     */
    private $dirMode = 0775;
    /**
     * @var bool
     */
    private $rotateByCopy = true;
    /** @var \Swoole\Atomic */
    private $atomic;

    /**
     * FileTarget constructor.
     */
    public function __construct()
    {
        $this->atomic = new \Swoole\Atomic();
    }

    /**
     * @throws \rabbit\core\Exception
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
     * @param bool $flush
     */
    public function export(array $messages, bool $flush = true): void
    {
        $fileInfo = pathinfo($this->logFile);
        $group = waitGroup();
        while ($this->atomic->get() !== 0) {
            \Co::sleep(0.001);
        }
        $this->atomic->add();
        foreach ($messages as $module => $message) {
            $fileInfo['filename'] = $module;
            $file = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.' . (isset($fileInfo['extension']) ? $fileInfo['extension'] : 'log');
            $group->add(uniqid(), function () use ($file, $message) {
                $fp = FileHelper::openFile($file, "a+");
                if ($this->enableRotation) {
                    // clear stat cache to ensure getting the real current file size and not a cached one
                    // this may result in rotating twice when cached file size is used on subsequent calls
                    clearstatcache();
                }
                $text = implode(PHP_EOL, $message) . PHP_EOL;
                if ($this->enableRotation && @filesize($file) > $this->maxFileSize * 1024) {
                    FileHelper::closeFile($file);
                    $this->rotateFiles($file);
                    $fp = FileHelper::openFile($file, 'a+');
                }
                $writeResult = @fwrite($fp, $text);
                if ($writeResult === false) {
                    $error = error_get_last();
                    throw new \RuntimeException("Unable to export log through file!: {$error['message']}");
                }
                $textSize = strlen($text);
                if ($writeResult < $textSize) {
                    throw new \RuntimeException("Unable to export whole log through file! Wrote $writeResult out of $textSize bytes.");
                }
                @flock($fp, LOCK_UN);
                if ($this->fileMode !== null) {
                    @chmod($file, $this->fileMode);
                }
            });
        }
        $group->wait(10 * 1000);
        $this->atomic->sub();
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
                $this->rotateByCopy ? $this->rotateByCopy($rotateFile, $newFile) : $this->rotateByRename($rotateFile,
                    $newFile);
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
            @ftruncate($filePointer, 0);
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

    /**
     * Renames rotated file into new file
     * @param string $rotateFile
     * @param string $newFile
     */
    private function rotateByRename($rotateFile, $newFile)
    {
        @rename($rotateFile, $newFile);
    }
}