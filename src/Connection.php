<?php

namespace srun\redis;

use yii\db\Exception;

class Connection extends \yii\redis\Connection
{
    /**
     * @param $name
     * @param $params
     * @return array|bool|mixed|string|null
     * @throws Exception
     */
    public function executeCommand($name, $params = [])
    {
        $this->open();

        array_unshift($params, $name);
        $command = '*' . count($params) . "\r\n";
        foreach ($params as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $key => $value) {
                    $command .= '$' . mb_strlen($key, '8bit') . "\r\n" . $key . "\r\n";
                    $command .= '$' . mb_strlen($value, '8bit') . "\r\n" . $value . "\r\n";
                }
            } else {
                $command .= '$' . mb_strlen($arg, '8bit') . "\r\n" . $arg . "\r\n";
            }
        }

        \Yii::trace("Executing Redis Command: {$name}", __METHOD__);
        fwrite($this->_socket, $command);

        return $this->parseResponse(implode(' ', $params));
    }
}