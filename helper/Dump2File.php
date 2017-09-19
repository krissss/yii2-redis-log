<?php

namespace kriss\log\helper;

use kriss\log\RedisTarget;
use Yii;
use yii\base\Component;
use yii\log\FileTarget;
use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;

class Dump2File extends Component
{
    /**
     * RedisTarget
     * @var string|array|RedisTarget
     */
    public $redisTarget;
    /**
     * RedisTarget Key
     * only used if redisTarget not config
     * @var string
     */
    public $redisTargetKey;
    /**
     * dump count
     * <=0 will dump all
     * @var int
     */
    public $count = 0;

    /**
     * @var FileTarget
     */
    private $_dumpFileTarget;

    /**
     * Initializes the RedisTarget component.
     * @throws InvalidConfigException if the configuration is invalid.
     */
    public function init()
    {
        parent::init();
        if (!$this->redisTarget && !$this->redisTargetKey) {
            throw new InvalidConfigException('must config `redisTarget` or `redisTargetKey`');
        }
        if (!$this->redisTarget) {
            $targets = Yii::$app->log->targets;
            foreach ($targets as $target) {
                if ($target instanceof RedisTarget && $target->key == $this->redisTargetKey) {
                    $this->redisTarget = $target;
                    break;
                }
            }
            if (!$this->redisTarget) {
                throw new NotFoundHttpException('unkonwn key `' . $this->redisTargetKey . '` in any log targets');
            }
        }

        if (is_string($this->redisTarget) || is_array($this->redisTarget)) {
            $this->redisTarget = Yii::createObject($this->redisTarget);
        }
        if (!$this->redisTarget instanceof RedisTarget) {
            throw new InvalidConfigException('`redisTarget` must be instance of `kriss\log\RedisTarget`');
        }

        $target = $this->redisTarget;
        if (!$target->dumpFileTarget) {
            $target->dumpFileTarget = FileTarget::className();
        }
        if (is_array($target->dumpFileTarget) && !isset($target->dumpFileTarget['class'])) {
            $target->dumpFileTarget['class'] = FileTarget::className();
        }
        $this->_dumpFileTarget = Yii::createObject($target->dumpFileTarget);
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
     * same like FileTarget only Remove formatMessage
     * @param $text string
     * @throws InvalidConfigException if unable to open the log file for writing
     */
    protected function export2File($text)
    {
        // Remove yii\log\FileTarget export2File formatMessage
        if (($fp = @fopen($this->_dumpFileTarget->logFile, 'a')) === false) {
            throw new InvalidConfigException("Unable to append to log file: {$this->_dumpFileTarget->logFile}");
        }
        @flock($fp, LOCK_EX);
        if ($this->_dumpFileTarget->enableRotation) {
            // clear stat cache to ensure getting the real current file size and not a cached one
            // this may result in rotating twice when cached file size is used on subsequent calls
            clearstatcache();
        }
        if ($this->_dumpFileTarget->enableRotation && @filesize($this->_dumpFileTarget->logFile) > $this->_dumpFileTarget->maxFileSize * 1024) {
            $this->rotateFiles();
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @file_put_contents($this->_dumpFileTarget->logFile, $text, FILE_APPEND | LOCK_EX);
        } else {
            @fwrite($fp, $text);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
        if ($this->_dumpFileTarget->fileMode !== null) {
            @chmod($this->_dumpFileTarget->logFile, $this->_dumpFileTarget->fileMode);
        }
    }

    /**
     * Rotates log files.
     * same like FileTarget
     */
    protected function rotateFiles()
    {
        $file = $this->_dumpFileTarget->logFile;
        for ($i = $this->_dumpFileTarget->maxLogFiles; $i >= 0; --$i) {
            // $i == 0 is the original log file
            $rotateFile = $file . ($i === 0 ? '' : '.' . $i);
            if (is_file($rotateFile)) {
                // suppress errors because it's possible multiple processes enter into this section
                if ($i === $this->_dumpFileTarget->maxLogFiles) {
                    @unlink($rotateFile);
                } else {
                    if ($this->_dumpFileTarget->rotateByCopy) {
                        @copy($rotateFile, $file . '.' . ($i + 1));
                        if ($fp = @fopen($rotateFile, 'a')) {
                            @ftruncate($fp, 0);
                            @fclose($fp);
                        }
                        if ($this->_dumpFileTarget->fileMode !== null) {
                            @chmod($file . '.' . ($i + 1), $this->_dumpFileTarget->fileMode);
                        }
                    } else {
                        @rename($rotateFile, $file . '.' . ($i + 1));
                    }
                }
            }
        }
    }
}