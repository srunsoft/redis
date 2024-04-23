<?php

namespace srun\redis;

use yii\db\Exception;
use yii\redis\SocketException;

/**
 * @method hmset($key, $array)
 * @method hmget($key, ...$field)
 */
class Connection extends \yii\redis\Connection
{
    /**
     * 执行redis command，这里针对hash传递进来的数组做单独处理，参考北向接口的array2hash
     * 因为 yii2-redis 直接使用的 redis 协议 与 redis server 交互.所以传递进来的数组是value,value存在hash的
     * 而自服务的hash缓存都是 数组 key=>value,key=>value
     *
     * @param $name
     * @param $params
     * @return array|bool|string|null
     * @throws Exception
     * @throws SocketException
     */
    public function executeCommand($name, $params = [])
    {
        $this->open();

        if (is_array(end($params))) {
            $args = array_pop($params);
            $setArgs = [];
            foreach ($args as $attribute => $value) {
                if ($value !== null) {
                    if (is_bool($value)) {
                        $value = (int) $value;
                    }
                    $setArgs[] = $attribute;
                    $setArgs[] = $value;
                }
            }
            $params = array_merge($params, $setArgs);
        }

        $params = array_merge(explode(' ', $name), $params);
        $command = '*' . count($params) . "\r\n";
        foreach ($params as $arg) {
            $command .= '$' . mb_strlen($arg, '8bit') . "\r\n" . $arg . "\r\n";
        }

        \Yii::trace("Executing Redis Command: {$name}", __METHOD__);
        if ($this->retries > 0) {
            $tries = $this->retries;
            while ($tries-- > 0) {
                try {
                    return $this->sendRawCommand($command, $params);
                } catch (SocketException $e) {
                    \Yii::error($e, __METHOD__);
                    // backup retries, fail on commands that fail inside here
                    $retries = $this->retries;
                    $this->retries = 0;
                    $this->close();
                    if ($this->retryInterval > 0) {
                        usleep($this->retryInterval);
                    }
                    try {
                        $this->open();
                    } catch (SocketException $exception) {
                        // Fail to run initial commands, skip current try
                        \Yii::error($exception, __METHOD__);
                        $this->close();
                    } catch (Exception $exception) {
                        $this->close();
                    }

                    $this->retries = $retries;
                }
            }
        }
        return $this->sendRawCommand($command, $params);
    }


    /**
     * 处理 hgetall,hmget 时将数组转换为关联数组
     * eg: [key,value,key1,value1,...more] translate [key => value, key1 => value1, ...more]
     *
     * @param $command
     * @param $params
     * @return array|bool|string|null
     * @throws Exception
     * @throws SocketException
     */
    protected function sendRawCommand($command, $params)
    {
        $data = parent::sendRawCommand($command, $params);

        if (is_array($data) && current($params) == 'HMGET') {
            $result = $params;
            unset($result[0]);
            unset($result[1]);
            return array_combine($result, $data);
        }
        if (is_array($data) && current($params) == 'HGETALL') {
            $result = [];
            foreach ($data as $k => $v) {
                if ($k > 0 && $k % 2 != 0) {
                    $result[$data[$k - 1]] = $v;
                }
            }
            return $result;
        }

        return $data;
    }
}