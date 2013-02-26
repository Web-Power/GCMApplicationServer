<?php
namespace WebPower\gcm\server;

class InvalidRequestExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testGettersNoDescription()
    {
        $exception = new InvalidRequestException(401);
        $this->assertEquals(401, $exception->getHttpStatusCode());
        $this->assertNull($exception->getDescription());
        $this->assertContains("401", $exception->getMessage(), $exception->getMessage());
    }

    public function testGettersDescription()
    {
        $exception = new InvalidRequestException(401, "D'OH!");
        $this->assertEquals(401, $exception->getHttpStatusCode());
        $this->assertEquals("D'OH!", $exception->getDescription());
        $this->assertContains("401", $exception->getMessage(), $exception->getMessage());
        $this->assertContains("D'OH!", $exception->getMessage());
    }
}
