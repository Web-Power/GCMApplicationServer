<?php
namespace WebPower\gcm\server;

class MessageBuilder
{
    private $collapseKey;
    private $data;
    private $timeToLive;
    private $delayWhileIdle;

    public function __construct()
    {
        $this->data = array();
    }

    /**
     * @param string $collapseKey
     * @return MessageBuilder
     */
    public function collapseKey($collapseKey)
    {
        $this->collapseKey = $collapseKey;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return MessageBuilder
     */
    public function addData($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * @param bool $delayWhileIdle
     * @return MessageBuilder
     */
    public function delayWhileIdle($delayWhileIdle)
    {
        $this->delayWhileIdle = $delayWhileIdle;
        return $this;
    }

    /**
     * @param int $timeToLive
     * @return MessageBuilder
     */
    public function timeToLive($timeToLive)
    {
        $this->timeToLive = $timeToLive;
        return $this;
    }

    public function build()
    {
        return new Message($this->collapseKey, $this->data, $this->timeToLive, $this->delayWhileIdle);
    }
}