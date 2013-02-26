<?php
namespace WebPower\gcm\server;

class Message implements \Serializable
{
    private $collapseKey;
    private $data;
    private $timeToLive;
    private $delayWhileIdle;

    public function __construct($collapseKey, array $data, $timeToLive, $delayWhileIdle)
    {
        $this->collapseKey = $collapseKey;
        $this->data = $data;
        $this->timeToLive = $timeToLive;
        $this->delayWhileIdle = $delayWhileIdle;
    }

    /**
     * @return MessageBuilder
     */
    public static function builder()
    {
        return new MessageBuilder();
    }

    /**
     * @return string
     */
    public function getCollapseKey()
    {
        return $this->collapseKey;
    }

    /**
     * @return string[]
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getTimeToLive()
    {
        return $this->timeToLive;
    }

    /**
     * @return bool
     */
    public function isDelayWhileIdle()
    {
        return $this->delayWhileIdle;
    }

    public function serialize()
    {
        return serialize(array($this->collapseKey, $this->data, $this->timeToLive, $this->delayWhileIdle));
    }

    public function unserialize($serialized)
    {
        list($this->collapseKey, $this->data, $this->timeToLive, $this->delayWhileIdle) = unserialize(
            $serialized
        );
    }

    public function __toString()
    {
        $properties = array();
        if ($this->collapseKey !== null) {
            $properties[]  = "collapseKey=" . $this->collapseKey .', ';
        }
        if ($this->timeToLive !== null) {
            $properties[] = 'timeToLive=' . $this->timeToLive . ', ';
        }
        if ($this->delayWhileIdle !== null) {
            $properties[] = 'delayWhileIdle=' . ($this->delayWhileIdle ? 'true' : 'false') . ', ';
        }
        if ($this->data) {
            $data = array();
            foreach ($this->data as $key => $value) {
                $data[] = $key . '=' . $value;
            }
            $properties[] = 'data: {' . implode(',', $data) . '}';
        }

        return 'Message(' . implode(', ', $properties) . ')';
    }
}