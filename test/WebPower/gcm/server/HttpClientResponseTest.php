<?php
namespace WebPower\gcm\server;

class HttpClientResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateResponse()
    {
        $statusCode = 201;
        $content = 'content';
        $response = new HttpClientResponse($statusCode, $content);

        $this->assertEquals($statusCode, $response->getResponseCode());
        $this->assertEquals($content, $response->getContent());
    }
}
