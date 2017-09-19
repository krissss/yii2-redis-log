<?php

namespace kriss\log;

use Yii;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\log\Target;
use yii\redis\Connection;

class RedisTarget extends Target
{
    /**
     * @var Connection|array|string the Redis connection object or a configuration array for creating the object, or the application component ID of the Redis connection.
     */
    public $redis = 'redis';

    /**
     * @var string key of the Redis list to store log messages. Default to "yii.log"
     */
    public $key = 'yii.log';

    /**
     * To Dump Redis Log To FileTarget
     * Default className is yii\log\FileTarget
     * used in [kriss\log\helper\Dump2File]
     * className or Yii config of yii\log\FileTarget
     * useful config like:
     * [logFile] [maxFileSize] [maxLogFiles] ...
     * @var string|array
     */
    public $dumpFileTarget;

    /**
     * Initializes the RedisTarget component.
     * This method will initialize the [[redis]] property to make sure it refers to a valid Redis connection.
     * @throws InvalidConfigException if [[redis]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->redis = Instance::ensure($this->redis, Connection::className());
    }

    /**
     * Stores log messages to Redis.
     */
    public function export()
    {
        Yii::trace('redis log start', __CLASS__);
        $texts = [];
        foreach ($this->messages as $message) {
            $texts[] = $this->formatMessage($message);
        }
        $this->redis->lpush($this->key, ...$texts);
        Yii::trace('redis log over', __CLASS__);
    }
}