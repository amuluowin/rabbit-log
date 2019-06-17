<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 16:38
 */

namespace rabbit\log\targets;

use rabbit\App;
use rabbit\compool\ComPoolInterface;
use rabbit\files\FileCom;
use rabbit\files\FileHelper;
use rabbit\helper\ArrayHelper;

/**
 * Class FileTarget
 * @package rabbit\log\targets
 */
class FileTarget extends AbstractTarget
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
    private $maxFileSize = 10240; // in KB
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
    /** @var ComPoolInterface */
    private $pool;
    /** @var ComPoolInterface[] */
    private $poolList = [];

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
        foreach ($messages as $module => $message) {
            $fileInfo['filename'] = $module;
            $file = $fileInfo['dirname'] . '/' . $fileInfo['filename'] . '.' . (isset($fileInfo['extension']) ? $fileInfo['extension'] : 'log');
            $key = md5($file);
            if (!isset($this->poolList[$key])) {
                $pool = clone $this->pool;
                $pool->getPoolConfig()->setConfig([
                    'file' => $file,
                    'option' => 'a+',
                    'key' => 'file'
                ]);
                $this->poolList[$key] = $pool;
            } else {
                $pool = $this->poolList[$key];
            }
            rgo(function () use ($file, $message, $pool) {
                /** @var FileCom $fileCom */
                $fileCom = $pool->getCom();
                if ($this->enableRotation) {
                    // clear stat cache to ensure getting the real current file size and not a cached one
                    // this may result in rotating twice when cached file size is used on subsequent calls
                    clearstatcache();
                }
                $text = '';
                foreach ($message as $msg) {
                    ArrayHelper::remove($msg, '%c');
                    $text .= implode($this->split, $msg) . PHP_EOL;
                }
                $fileCom->lock(function () use ($text, $fileCom, $file) {
                    $fileCom->write($text);
                    $fileCom->release();
                    if ($this->enableRotation && @filesize($file) > $this->maxFileSize * 1024) {
                        $this->rotateFiles($file);
                    }
                    if ($this->fileMode !== null) {
                        @chmod($file, $this->fileMode);
                    }
                });
            });
        }
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