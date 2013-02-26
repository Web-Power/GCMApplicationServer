<?php
namespace WebPower\gcm\server;

class ResultBuilder
{
    private $canonicalRegistrationId;
    private $errorCode;
    private $messageId;

    public function build()
    {
        return new Result($this->canonicalRegistrationId, $this->errorCode, $this->messageId);
    }

    /**
     * @param $canonicalRegistrationId
     * @return ResultBuilder
     */
    public function canonicalRegistrationId($canonicalRegistrationId)
    {
        $this->canonicalRegistrationId = $canonicalRegistrationId;
        return $this;
    }

    /**
     * @param $errorCode
     * @return ResultBuilder
     */
    public function errorCode($errorCode)
    {
        $this->errorCode = $errorCode;
        return $this;
    }

    /**
     * @param $messageId
     * @return ResultBuilder
     */
    public function messageId($messageId)
    {
        $this->messageId = $messageId;
        return $this;
    }
}