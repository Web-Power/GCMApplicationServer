<?php
namespace WebPower\gcm\server;

class ResultTest extends \PHPUnit_Framework_TestCase
{
    public function testRequiredParameters()
    {
        $result = Result::builder()->build();
        $this->assertNull($result->getMessageId());
        $this->assertNull($result->getErrorCodeName());
        $this->assertNull($result->getCanonicalRegistrationId());
    }

    public function testOptionalParameters()
    {
        $result = Result::builder()
            ->messageId("42")
            ->errorCode("D'OH!")
            ->canonicalRegistrationId("108")
            ->build();

        $this->assertEquals("42", $result->getMessageId());
        $this->assertEquals("D'OH!", $result->getErrorCodeName());
        $this->assertEquals("108", $result->getCanonicalRegistrationId());
        $toString = $result->__toString();
        $this->assertContains("messageId=42", $toString);
        $this->assertContains("errorCode=D'OH!", $toString);
        $this->assertContains("canonicalRegistrationId=108", $toString);
    }
}
