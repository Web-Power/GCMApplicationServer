<?php
namespace WebPower\gcm\server;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testRequiredParameters()
    {
        $message = Message::builder()->build();
        $this->assertNull($message->getCollapseKey());
        $this->assertNull($message->isDelayWhileIdle());
        $this->assertEmpty($message->getData());
        $this->assertNull($message->getTimeToLive());
        $toString = $message->__toString();
        $this->assertNotContains('collapsekey', $toString);
        $this->assertNotContains('timeToLive', $toString);
        $this->assertNotContains('delayWhileIdle', $toString);
        $this->assertNotContains('data', $toString);
    }

    public function testOptionalParameters()
    {
        $message = Message::builder()
            ->collapseKey('108')
            ->delayWhileIdle(true)
            ->timeToLive(42)
            ->addData('k1', 'old value')
            ->addData('k1', 'v1')
            ->addData('k2', 'v2')
            ->build()
        ;

        $this->assertEquals("108", $message->getCollapseKey());
        $this->assertTrue($message->isDelayWhileIdle());
        $this->assertEquals(42, $message->getTimeToLive());
        $data = $message->getData();
        $this->assertEquals(2, count($data));
        $this->assertEquals('v1', $data['k1']);
        $this->assertEquals('v2', $data['k2']);

        $toString = $message->__toString();
        $this->assertContains('collapseKey=108', $toString);
        $this->assertContains('timeToLive=42', $toString);
        $this->assertContains('delayWhileIdle=true', $toString);
        $this->assertContains('k1=v1', $toString);
        $this->assertContains('k2=v2', $toString);
    }

    public function testIsSerializable()
    {
        $expected = Message::builder()
            ->collapseKey('108')
            ->delayWhileIdle(true)
            ->timeToLive(42)
            ->addData('k1', 'old value')
            ->addData('k1', 'v1')
            ->addData('k2', 'v2')
            ->build()
        ;
        $actual = unserialize(serialize($expected));

        $this->assertEquals($expected, $actual);
    }
}
