Yii2 log redisTarget
====================
Yii2 log for redisTarget and dump redisLog to FileTarget.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist kriss/yii2-redis-log "*" -vvv
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

```php
$dumper = new Dump2File([
  // this should be euqal like log in config 
  'redisTarget' => [
      'class' => 'kriss\log\RedisTarget',
      'redis' => 'redis',
      'key' => 'yii.log',
  ],
  'fileTarget' => [
      'class' => 'yii\log\FileTarget',
      'logFile' => '@common/runtime/logs/error.log',
  ],
  'count' => 0
]);
$dumper->dump();
```