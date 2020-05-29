<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 16:38
 */

namespace rabbit\log\targets;

use rabbit\App;
use rabbit\compool\BaseCompool;
use rabbit\compool\ComPoolInterface;
use rabbit\compool\ComPoolProperties;
use rabbit\contract\InitInterface;
use rabbit\core\ObjectFactory;
use rabbit\files\FileCom;
use rabbit\files\FileHelper;
use rabbit\helper\ArrayHelper;
use rabbit\helper\StringHelper;

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
    /** @var array */
    private $pool = [];
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
        if (empty($this->pool)) {
            $this->pool = [
                'class' => BaseCompool::class,
                'comClass' => FileCom::class,
                'poolConfig' => ObjectFactory::createObject([
                    'class' => ComPoolProperties::class,
                    'count' => 10,
                ], [], false)
            ];
        }
        $logPath = dirname($this->logFile);
        FileHelper::createDirectory($logPath, $this->dirMode, true);
    }

    /**
     * @param array $messages
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
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
            $key = md5($file);
            if (!isset($this->poolList[$key])) {
                $pool = ObjectFactory::createObject($this->pool, [], false);
                $pool->getPoolConfig()->setConfig([
                    'file' => $file,
                    'option' => 'a+',
                    'key' => 'file'
                ]);
                $this->poolList[$key] = $pool;
            } else {
                $pool = $this->poolList[$key];
            }
            if ($this->fileMode !== null) {
                @chmod($file, $this->fileMode);
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
                    $text .= implode($this->split, $msg) . PHP_EOL;
                }
                if (!empty($text)) {
                    $fileCom->lock(function () use ($file) {
                        if ($this->enableRotation && @filesize($file) > $this->maxFileSize * 1024) {
                            $this->rotateFiles($file);
                        }
                    });
                    $fileCom->write($text);
                }
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
