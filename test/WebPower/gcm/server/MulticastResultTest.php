<?php
namespace WebPower\gcm\server;

class MulticastResultTest extends \PHPUnit_Framework_TestCase
{
    public function testRequiredParametersNoResults()
    {
        $result = MulticastResult::builder(4, 8, 15, 16)->build();

        $this->assertEquals(4, $result->getSuccess());
        $this->assertEquals(8, $result->getFailure());
        $this->assertEquals(12, $result->getTotal());
        $this->assertEquals(16, $result->getMulticastId());
        $this->assertEmpty($result->getResults());
        $this->assertEmpty($result->getRetryMulticastIds());
    }

    public function testRequiredParametersWithResults()
    {
        $result = MulticastResult::builder(4, 8, 15, 16)
            ->addResult(Result::builder()->messageId("23")->build())
            ->addResult(Result::builder()->messageId("42")->build())
            ->build();

        $this->assertEquals(4, $result->getSuccess());
        $this->assertEquals(8, $result->getFailure());
        $this->assertEquals(12, $result->getTotal());
        $this->assertEquals(16, $result->getMulticastId());

        $results = $result->getResults();
        $this->assertEquals(2, count($results));
        $this->assertEquals("23", $results[0]->getMessageId());
        $this->assertEquals("42", $results[1]->getMessageId());
        $toString = $result->__toString();
        $this->assertContains("multicast_id=16", $toString);
        $this->assertContains("total=12", $toString);
        $this->assertContains("success=4", $toString);
        $this->assertContains("failure=8", $toString);
        $this->assertContains("canonical_ids=15", $toString);
        $this->assertContains("results", $toString);
    }

    public function testOptionalParameters()
    {
        $result = MulticastResult::builder(4, 8, 15, 16)
            ->retryMulticastIds(array(23, 42))
            ->build();

        $this->assertEquals(4, $result->getSuccess());
        $this->assertEquals(8, $result->getFailure());
        $this->assertEquals(12, $result->getTotal());
        $this->assertEquals(16, $result->getMulticastId());
        $this->assertEmpty($result->getResults());
        $retryMulticastIds = $result->getRetryMulticastIds();
        $this->assertEquals(2, count($retryMulticastIds));
        $this->assertEquals(23, $retryMulticastIds[0]);
        $this->assertEquals(42, $retryMulticastIds[1]);
    }

    public function testSerializable()
    {
        $expected = MulticastResult::builder(4, 8, 15, 16)
            ->addResult(Result::builder()->messageId("23")->build())
            ->addResult(Result::builder()->messageId("42")->build())
            ->build();

        $actual = unserialize(serialize($expected));

        $this->assertEquals($expected, $actual);
    }
}
