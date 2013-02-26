<?php
namespace WebPower\gcm\server;

/**
 * Result of a GCM message request that returned HTTP status code 200.
 *
 * If the message is successfully created, the getMessageId() returns the
 * message id and getErrorCodeName() returns null; otherwise, getMessageId()
 * returns null and getErrorCodeName() returns the code of the error.
 *
 * There are cases when a request is accept and the message successfully created,
 * but GCM has a canonical registration id for that device.
 * In this case, the server should update the registration id to
 * avoid rejected requests in the future.
 */
class Result implements \Serializable
{
    private $canonicalRegistrationId;
    private $errorCode;
    private $messageId;

    public function __construct($canonicalRegistrationId, $errorCodeName, $messageId)
    {
        $this->canonicalRegistrationId = $canonicalRegistrationId;
        $this->errorCode = $errorCodeName;
        $this->messageId = $messageId;
    }

    public static function builder()
    {
        return new ResultBuilder();
    }

    /**
     * Gets the message id, if any.
     *
     * @return string
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * Gets the canonical registration id, if any.
     *
     * @return string
     */
    public function getCanonicalRegistrationId()
    {
        return $this->canonicalRegistrationId;
    }

    /**
     * Gets the error code, if any.
     *
     * @return string
     */
    public function getErrorCodeName()
    {
        return $this->errorCode;
    }

    public function __toString()
    {
        $builder = '[';
        if ($this->messageId !== null) {
            $builder .= ' messageId=' . $this->messageId;
        }
        if ($this->canonicalRegistrationId !== null) {
            $builder .= ' canonicalRegistrationId=' . $this->canonicalRegistrationId;
        }
        if ($this->errorCode !== null) {
            $builder .= ' errorCode=' . $this->errorCode;
        }
        return $builder . ' ]';
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize(array($this->canonicalRegistrationId, $this->errorCode, $this->messageId));
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
        list($this->canonicalRegistrationId, $this->errorCode, $this->messageId) = unserialize(
            $serialized
        );
    }
}