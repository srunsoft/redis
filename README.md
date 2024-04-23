# srun/redis

`srun/redis` 是一个基于 `yii2-redis` 的 Redis 连接扩展包，用于处理 Redis 操作的返回值。

## 特性

- 处理 hash 插入时传递数组问题。
- 自动将 `HGETALL` 命令返回的数组转换为键值对的关联数组。

## 安装

要安装此包，请在项目的根目录下运行以下命令：

```bash
composer require srun/redis
```

## 配置
将项目中`\yii\redis\Connection` 替换为 `/srun/redis/Connection`
```php
use srun/redis/Connection
```
### IDE 识别
```php
/**
 * @property srun\redis\Connection $redisOnline
 */
```
