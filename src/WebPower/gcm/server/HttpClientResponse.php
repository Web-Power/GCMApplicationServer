<?php
namespace WebPower\gcm\server;

class HttpClientResponse
{
    private $statusCode;
    private $content;

    public function __construct($statusCode, $content)
    {
        $this->statusCode = $statusCode;
        $this->content = $content;
    }

    public function getResponseCode()
    {
        return $this->statusCode;
    }

    public function getContent()
    {
        return $this->content;
    }
}