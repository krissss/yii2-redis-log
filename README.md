Yii2 log redisTarget
====================
Yii2 log for redisTarget and dump redisLog to FileTarget.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist kriss/yii2-redis-log -vvv
```

or add

```
"kriss/yii2-redis-log": "*"
```

to the require section of your `composer.json` file.


Simple Usage
-----

1. config config file

```php
'log' => [
    'targets' => [
        [
            'class' => 'kriss\log\RedisTarget',
            'redis' => 'redis',
            'key' => 'yii.log',
            'levels' => ['error', 'warning'],
        ],
    ]
]
```

2. use Yii common Logger component like :

```php
Yii::error('this is en error');
```

3. now you see log in your redis

Dump Redis Log to File
-----

1. config

```php
'log' => [
    'targets' => [
        [
            'class' => 'kriss\log\RedisTarget',
            'redis' => 'redis',
            'key' => 'yii.log',
            'dumpFileTarget' => [
                'logFile' => '@common/runtime/logs/error.log',
            ]
            'levels' => ['error', 'warning'],
        ],
    ]
]
```

2.1. Dump One

```php
$dumper = new Dump2File([
  'redisTargetKey' => 'yii.log',
]);
$dumper->dump();
```

2.2. Dump From Yii Log Target

```php
$targets = Yii::$app->log->targets;
foreach ($targets as $target) {
    if ($target instanceof RedisTarget) {
        $dumper = new Dump2File([
            'redisTarget' => $target,
            'count' => 1000
        ]);
        $dumper->dump();
    }
}
```