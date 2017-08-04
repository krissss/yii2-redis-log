<?php

namespace kriss\log\helper;

use kriss\log\RedisTarget;
use Yii;
use yii\base\Component;
use yii\log\FileTarget;
use yii\base\InvalidConfigException;

class Dump2File extends Component
{
    /**
     * @var RedisTarget
     */
    public $redisTarget = 'common\log\RedisTarget';
    /**
     * @var FileTarget
     */
    public $fileTarget = 'yii\log\FileTarget';
    /**
     * dump count
     * <=0 will dump all
     * @var int
     */
    public $count = 1000;

    /**
     * Initializes the RedisTarget component.
     * @throws InvalidConfigException if the configuration is invalid.
     */
    public function init()
    {
        parent::init();
        $this->redisTarget = Yii::createObject($this->redisTarget);
        $this->fileTarget = Yii::createObject($this->fileTarget);
    }

    /**
     * dump log to FileTarget
     */
    public function dump()
    {
        Yii::trace('dump to file start', __CLASS__);
        $i = 0;
        $text = '';
        while (($this->count <= 0 || $i < $this->count) && $message = $this->redisTarget->redis->rpop($this->redisTarget->key)) {
            if ($i % 20 === 0) {
                Yii::trace('dump to file', __CLASS__);
                $this->export2File($text);
                $text = '';
            }
            $text .= $message . "\n";
            $i++;
        }
        Yii::trace('dump to file', __CLASS__);
        $this->export2File($text);
        Yii::trace('dump to file over, size:' . $i, __CLASS__);
    }

    /**
     * Writes log messages to a file.
     * @param $text string
     * @throws InvalidConfigException if unable to open the log file for writing
     */
    protected function export2File($text)
    {
        if (($fp = @fopen($this->fileTarget->logFile, 'a')) === false) {
            throw new InvalidConfigException("Unable to append to log file: {$this->fileTarget->logFile}");
        }
        @flock($fp, LOCK_EX);
        if ($this->fileTarget->enableRotation) {
            // clear stat cache to ensure getting the real current file size and not a cached one
            // this may result in rotating twice when cached file size is used on subsequent calls
            clearstatcache();
        }
        if ($this->fileTarget->enableRotation && @filesize($this->fileTarget->logFile) > $this->fileTarget->maxFileSize * 1024) {
            $this->rotateFiles();
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @file_put_contents($this->fileTarget->logFile, $text, FILE_APPEND | LOCK_EX);
        } else {
            @fwrite($fp, $text);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
        if ($this->fileTarget->fileMode !== null) {
            @chmod($this->fileTarget->logFile, $this->fileTarget->fileMode);
        }
    }

    /**
     * Rotates log files.
     */
    protected function rotateFiles()
    {
        $file = $this->fileTarget->logFile;
        for ($i = $this->fileTarget->maxLogFiles; $i >= 0; --$i) {
            // $i == 0 is the original log file
            $rotateFile = $file . ($i === 0 ? '' : '.' . $i);
            if (is_file($rotateFile)) {
                // suppress errors because it's possible multiple processes enter into this section
                if ($i === $this->fileTarget->maxLogFiles) {
                    @unlink($rotateFile);
                } else {
                    if ($this->fileTarget->rotateByCopy) {
                        @copy($rotateFile, $file . '.' . ($i + 1));
                        if ($fp = @fopen($rotateFile, 'a')) {
                            @ftruncate($fp, 0);
                            @fclose($fp);
                        }
                        if ($this->fileTarget->fileMode !== null) {
                            @chmod($file . '.' . ($i + 1), $this->fileTarget->fileMode);
                        }
                    } else {
                        @rename($rotateFile, $file . '.' . ($i + 1));
                    }
                }
            }
        }
    }
}