<?php
namespace WebPower\gcm\server;

/**
 * Result of a GCM multicast message request
 */
class MulticastResult implements \Serializable
{
    /** @var int */
    private $canonicalIds;
    /** @var int */
    private $failure;
    /** @var int */
    private $multicastId;
    /** @var Result[] */
    private $results;
    /** @var int[] */
    private $retryMulticastIds;
    /** @var int */
    private $success;

    public function __construct(
        $canonicalIds,
        $failure,
        $multicastId,
        array $results,
        array $retryMulticastIds,
        $success
    ) {
        $this->canonicalIds = $canonicalIds;
        $this->failure = $failure;
        $this->multicastId = $multicastId;
        $this->results = $results;
        $this->retryMulticastIds = $retryMulticastIds;
        $this->success = $success;
    }

    public static function builder(
        $success,
        $failure,
        $canonicalIds,
        $multicastId
    ) {
        return new MulticastResultBuilder($success, $failure, $canonicalIds, $multicastId);
    }


    /**
     * Gets the number of successful messages that also returned a canonical registration id.
     *
     * @return int
     */
    public function getCanonicalIds()
    {
        return $this->canonicalIds;
    }

    /**
     * Gets the number of failed messages.
     *
     * @return int
     */
    public function getFailure()
    {
        return $this->failure;
    }

    /**
     * Gets the multicast id.
     *
     * @return int
     */
    public function getMulticastId()
    {
        return $this->multicastId;
    }

    /**
     * Gets the results of each individual message, which is immutable.
     *
     * @return Result[]
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Gets additional ids if more than one multicast message was sent.
     *
     * @return int[]
     */
    public function getRetryMulticastIds()
    {
        return $this->retryMulticastIds;
    }

    /**
     * Gets the number of successful messages.
     *
     * @return int
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * Gets the total number of messages sent, regardless of the status.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->failure + $this->success;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize(array($this->canonicalIds, $this->failure, $this->multicastId, $this->results, $this->retryMulticastIds, $this->success));
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     */
    public function unserialize($serialized)
    {
        list($this->canonicalIds, $this->failure, $this->multicastId, $this->results, $this->retryMulticastIds, $this->success) = unserialize(
            $serialized
        );
    }

    public function __toString()
    {
        $properties = array(
            'multicast_id='.$this->multicastId,
            'total='.$this->getTotal(),
            'success='.$this->success,
            'failure='.$this->failure,
            'canonical_ids='.$this->canonicalIds,
        );
        if ($this->results) {
            $properties[] = 'results: ' . implode(',', $this->results);
        }
        return implode(',', $properties);
    }
}