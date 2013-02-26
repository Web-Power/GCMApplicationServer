<?php
namespace WebPower\gcm\server;

class InvalidRequestException extends \Exception
{
    private $httpStatusCode;
    private $description;

    function __construct($httpStatusCode, $description = null)
    {
        $this->httpStatusCode = (int) $httpStatusCode;
        $this->description = $description;

        $msg = 'HTTP Status Code: ' . $this->httpStatusCode;
        if ($description !== null) {
            $msg .= '('.$description.')';
        }
        parent::__construct($msg);
    }

    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    public function getDescription()
    {
        return $this->description;
    }
}