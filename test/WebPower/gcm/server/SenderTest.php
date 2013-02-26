<?php
namespace WebPower\gcm\server;

function usleep($time) {
    SenderTest::$slept = true;
    SenderTest::$sleepTimeList[] = $time / 1000;
}

class MockHttpClient implements HttpClient
{
    public $apiKey;
    public $url;
    public $mimeType;
    public $requestBody;

    public $responseList = array();

    public function post($apiKey, $url, $mimeType, $requestBody)
    {
        $this->apiKey = $apiKey;
        $this->url = $url;
        $this->mimeType = $mimeType;
        $this->requestBody = $requestBody;

        return array_shift($this->responseList);
    }
}

class SenderTest extends \PHPUnit_Framework_TestCase
{
    public static $slept;
    public static $sleepTimeList;

    private $regId = "15;16";
    private $collapseKey = "collapseKey";
    private $delayWhileIdle = true;
    private $retries = 42;
    private $ttl = 108;
    private $authKey = "4815162342";

    /** @var Message */
    private $message;

    /** @var MockHttpClient */
    private $mockClient;

    /** @var Sender */
    private $sender;

    /** @var Result */
    private $result;

    protected function setUp()
    {
        parent::setUp();
        $this->message = Message::builder()
            ->collapseKey($this->collapseKey)
            ->delayWhileIdle($this->delayWhileIdle)
            ->timeToLive($this->ttl)
            ->addData('k1', 'v1')
            ->addData('k2', 'v2')
            ->addData('k3', 'v3')
            ->build();

        $this->mockClient = new MockHttpClient();
        $this->sender = $this->getMock('WebPower\gcm\server\Sender', null, array($this->authKey));
        $this->sender->setHttpClient($this->mockClient);

        $this->result = Result::builder()->build();
        self::$slept = false;
        self::$sleepTimeList = array();
    }

    public function setResponseExpectations($statusCode, $content)
    {
        $this->mockClient->responseList[] = new HttpClientResponse($statusCode, $content);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorNull()
    {
        new Sender(null);
    }

    public function testSingleSend_noRetryOk()
    {
        $this->sender = $this->getMock('WebPower\gcm\server\Sender', array('singleSendNoRetry'), array($this->authKey));
        $this->sender->expects($this->any())->method('singleSendNoRetry')->with($this->message, $this->regId)->will($this->returnValue($this->result));
        $this->sender->singleSend($this->message, $this->regId, 0);
        $this->assertFalse(self::$slept);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSingleSend_noRetryFail()
    {
        $this->sender = $this->getMock('WebPower\gcm\server\Sender', array('singleSendNoRetry'), array($this->authKey));
        $this->sender->expects($this->any())->method('singleSendNoRetry')->with($this->message, $this->regId)->will($this->returnValue(null));
        $this->sender->singleSend($this->message, $this->regId, 0);
        $this->assertFalse(self::$slept);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSingleSend_noRetryException()
    {
        $this->sender = $this->getMock('WebPower\gcm\server\Sender', array('singleSendNoRetry'), array($this->authKey));
        $this->sender->expects($this->any())->method('singleSendNoRetry')->with($this->message, $this->regId)->will($this->throwException(new \RuntimeException()));
        $this->sender->singleSend($this->message, $this->regId, 0);
    }

    public function testSingleSend_retryOk()
    {
        $returns = array(
            null, // fails 1st time
            null, // fails 2nd time
            $this->result // succeeds 3rd time
        );
        $this->sender = $this->getMock('WebPower\gcm\server\Sender', array('singleSendNoRetry'), array($this->authKey));
        $this->sender->expects($this->exactly(3))
            ->method('singleSendNoRetry')
            ->with($this->message, $this->regId)
            ->will($this->returnCallback(function() use(&$returns) {
                        return array_shift($returns);
                    }));

        $this->sender->singleSend($this->message, $this->regId, 2);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSingleSend_retryFails()
    {
        $returns = array(
            null, // fails 1st time
            null, // fails 2nd time
            null // fails 3rd time
        );
        $this->sender = $this->getMock('WebPower\gcm\server\Sender', array('singleSendNoRetry'), array($this->authKey));
        $this->sender->expects($this->exactly(3))
            ->method('singleSendNoRetry')
            ->with($this->message, $this->regId)
            ->will($this->returnCallback(function() use(&$returns) {
                        return array_shift($returns);
                    }));

        $this->sender->singleSend($this->message, $this->regId, 2);
    }

    public function testSingleSend_retryExponentialBackoff()
    {
        $total = $this->retries + 1; // first attempt + retries
        $this->sender = $this->getMock('WebPower\gcm\server\Sender', array('singleSendNoRetry'), array($this->authKey));
        $this->sender->expects($this->exactly($total))->method('singleSendNoRetry')->with($this->message, $this->regId)->will($this->returnValue(null));

        try {
            $this->sender->singleSend(
                $this->message,
                $this->regId,
                $this->retries
            );
            $this->fail('Should have thrown RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertContains('' . $total, $e->getMessage(), 'invalid message:'.$e->getMessage());
        }
        $this->assertEquals($this->retries, count(self::$sleepTimeList));
        $backoffRange = Sender::BACKOFF_INITIAL_DELAY;
        foreach (self::$sleepTimeList as $value) {
            $this->assertTrue($value >= $backoffRange / 2);
            $this->assertTrue($value <= $backoffRange * 3 / 2);
            if (2 * $backoffRange < Sender::MAX_BACKOFF_DELAY) {
                $backoffRange *= 2;
            }
        }
    }

    public function testSingleSendNoRetry_ok()
    {
        $this->setResponseExpectations(200, "id=4815162342");
        $result = $this->sender->singleSendNoRetry($this->message, $this->regId);
        $this->assertNotNull($result);
        $this->assertEquals("4815162342", $result->getMessageId());
        $this->assertNull($result->getCanonicalRegistrationId());
        $this->assertNull($result->getErrorCodeName());
        $this->assertRequestBody();
    }

    public function testSingleSendNoRetry_ok_canonical()
    {
        $this->setResponseExpectations(
            200,
            "id=4815162342\nregistration_id=108"
        );
        $result = $this->sender->singleSendNoRetry($this->message, $this->regId);
        $this->assertNotNull($result);
        $this->assertEquals("4815162342", $result->getMessageId());
        $this->assertEquals("108", $result->getCanonicalRegistrationId());
        $this->assertNull($result->getErrorCodeName());
        $this->assertRequestBody();
    }

    public function testSingleSendNoRetry_unauthorized()
    {
        $this->setResponseExpectations(401, "");
        try {
            $this->sender->singleSendNoRetry($this->message, $this->regId);
            $this->fail("Should have thrown InvalidRequestException");
        } catch (InvalidRequestException $e) {
            $this->assertEquals(401, $e->getHttpStatusCode());
        }
        $this->assertRequestBody();
    }

    public function testSendNoRetry_error()
    {
        $this->setResponseExpectations(200, "Error=D'OH!");
        $result = $this->sender->singleSendNoRetry($this->message, $this->regId);
        $this->assertNull($result->getMessageId());
        $this->assertNull($result->getCanonicalRegistrationId());
        $this->assertEquals("D'OH!", $result->getErrorCodeName());
        $this->assertRequestBody();
    }

    public function testSendNoRetry_serviceUnavailable()
    {
        $this->setResponseExpectations(503, "");
        $result = $this->sender->singleSendNoRetry($this->message, $this->regId);
        $this->assertNull($result);
        $this->assertRequestBody();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSendNoRetry_emptyBody()
    {
        $this->setResponseExpectations(200, "");
        $this->sender->singleSendNoRetry($this->message, $this->regId);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSendNoRetry_noToken()
    {
        $this->setResponseExpectations(200, 'no token');
        $this->sender->singleSendNoRetry($this->message, $this->regId);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSendNoRetry_invalidToken()
    {
        $this->setResponseExpectations(200, 'bad=token');
        $this->sender->singleSendNoRetry($this->message, $this->regId);
    }

    public function testSendNoRetry_invalidHttpStatusCode()
    {
        $this->setResponseExpectations(108, 'id=4815162342');
        try {
            $this->sender->singleSendNoRetry($this->message, $this->regId);
            $this->fail('Should have thrown InvalidRequestException');
        } catch (InvalidRequestException $e) {
            $this->assertEquals(108, $e->getHttpStatusCode());
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSendNoRetry_noRegistrationId()
    {
        $this->sender->singleSendNoRetry(Message::builder()->build(), null);
    }

    public function testSend_json_allAttemptsFail()
    {
        $unavailableResult = Result::builder()->errorCode('Unavailable')->build();
        $regIds = array("108");
        $mockedResult = MulticastResult::builder(0, 0, 0, 42)->addResult($unavailableResult)->build();
        $this->sender = $this->getMock('WebPower\gcm\server\Sender', array('SendNoRetry'), array($this->authKey));
        $this->sender->expects($this->any())->method('SendNoRetry')->with($this->message, $regIds)->will($this->returnValue($mockedResult));
    }

    public function testSend_json_secondAttemptOk()
    {
        $unavailableResult = Result::builder()->errorCode('Unavailable')->build();
        $okResult = Result::builder()->messageId("42")->build();
        $mockedResult1 = MulticastResult::builder(0, 0, 0, 100)
            ->addResult($unavailableResult)->build();
        $mockedResult2 = MulticastResult::builder(0, 0, 0, 200)
            ->addResult($okResult)->build();
        $regIds = array("108");
        $returns = array(
            $mockedResult1,
            $mockedResult2
        );

        /** @var $sender Sender */
        $sender = $this->getMock('WebPower\gcm\server\Sender', array('sendNoRetry'), array($this->authKey));
        $sender->expects($this->exactly(2))
            ->method('sendNoRetry')
            ->with($this->message, $regIds)
            ->will($this->returnCallback(function() use(&$returns) {
                        return array_shift($returns);
                    }));

        $actualResult = $sender->send($this->message, $regIds, 10);
        $this->assertNotNull($actualResult);
        $this->assertEquals(1, $actualResult->getTotal());
        $this->assertEquals(1, $actualResult->getSuccess());
        $this->assertEquals(0, $actualResult->getFailure());
        $this->assertEquals(0, $actualResult->getCanonicalIds());
        $this->assertEquals(100, $actualResult->getMulticastId());
        $results = $actualResult->getResults();
        $this->assertEquals(1, count($results));
        $this->assertResult($results[0], "42", null, null);
        $retryMulticastIds = $actualResult->getRetryMulticastIds();
        $this->assertEquals(1, count($retryMulticastIds));
        $this->assertEquals(200, $retryMulticastIds[0]);
    }

    public function testSend_json_ok()
    {
        /*
         * The following scenario is mocked below:
         *
         * input: 4, 8, 15, 16, 23, 42
         *
         * 1st call (multicast_id:100): 4,16:ok 8,15,23:unavailable, 42:error,
         * 2nd call (multicast_id:200): 8,15: unavailable, 23:ok
         * 3rd call (multicast_id:300): 8:error, 15:unavailable
         * 4th call (multicast_id:400): 15:unavailable
         *
         * output: total:6, success:3, error: 3, canonicals: 0, multicast_id: 100
         *         results: ok, error, unavailable, ok, ok, error
         */
        $valueMap = array();
        $unavailableResult = Result::builder()->errorCode('Unavailable')->build();
        $errorResult = Result::builder()->errorCode("D'OH!")->build();
        $okResultMsg4 = Result::builder()->messageId("msg4")->build();
        $okResultMsg16 = Result::builder()->messageId("msg16")->build();
        $okResultMsg23 = Result::builder()->messageId("msg23")->build();
        $result1stCall = MulticastResult::builder(0, 0, 0, 100)
            ->addResult($okResultMsg4)
            ->addResult($unavailableResult)
            ->addResult($unavailableResult)
            ->addResult($okResultMsg16)
            ->addResult($unavailableResult)
            ->addResult($errorResult)
            ->build();
        $valueMap[] = array($this->message, array("4", "8", "15", "16", "23", "42"), $result1stCall);

        $result2ndCall = MulticastResult::builder(0, 0, 0, 200)
            ->addResult($unavailableResult)
            ->addResult($unavailableResult)
            ->addResult($okResultMsg23)
            ->build();
        $valueMap[] = array($this->message, array("8", "15", "23"), $result2ndCall);

        $result3rdCall = MulticastResult::builder(0, 0, 0, 300)
            ->addResult($errorResult)
            ->addResult($unavailableResult)
            ->build();
        $valueMap[] = array($this->message, array("8", "15"), $result3rdCall);

        $result4thCall = MulticastResult::builder(0, 0, 0, 400)
            ->addResult($unavailableResult)
            ->build();
        $valueMap[] = array($this->message, array("15"), $result4thCall);

        /** @var $sender Sender */
        $sender = $this->getMock('WebPower\gcm\server\Sender', array('sendNoRetry'), array($this->authKey));
        $sender->expects($this->exactly(4))
            ->method('sendNoRetry')
            ->will($this->returnValueMap($valueMap));

        $actualResult = $sender->send($this->message, array("4", "8", "15", "16", "23", "42"), 3);

        $this->assertNotNull($actualResult);
        $this->assertEquals(6, $actualResult->getTotal());
        $this->assertEquals(3, $actualResult->getSuccess());
        $this->assertEquals(3, $actualResult->getFailure());
        $this->assertEquals(0, $actualResult->getCanonicalIds());
        $this->assertEquals(100, $actualResult->getMulticastId());
        $actualResults = $actualResult->getResults();
        $this->assertEquals(6, count($actualResults));
        $this->assertResult($actualResults[0], "msg4", null, null); // 4
        $this->assertResult($actualResults[1], null, "D'OH!", null); // 8
        $this->assertResult($actualResults[2], null, "Unavailable", null); // 15
        $this->assertResult($actualResults[3], "msg16", null, null); // 16
        $this->assertResult($actualResults[4], "msg23", null, null); // 23
        $this->assertResult($actualResults[5], null, "D'OH!", null); // 42
        $retryMulticastIds = $actualResult->getRetryMulticastIds();
        $this->assertEquals(3, count($retryMulticastIds));
        $this->assertEquals(200, $retryMulticastIds[0]);
        $this->assertEquals(300, $retryMulticastIds[1]);
        $this->assertEquals(400, $retryMulticastIds[2]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSendNoRetry_json_emptyRegIds()
    {
        $this->sender->sendNoRetry($this->message, array());
    }

    public function testSendNoRetry_json_badRequest()
    {
        $this->setResponseExpectations(42, "bad json");
        try {
            $this->sender->sendNoRetry($this->message, array("108"));
        } catch (InvalidRequestException $e) {
            $this->assertEquals(42, $e->getHttpStatusCode());
            $this->assertEquals("bad json", $e->getDescription());
            $this->assertRequestJsonBody(array("108"));
        }
    }

    public function testSendNoRetry_json_ok()
    {
        $json = json_encode(array(
                "multicast_id" => 108,
                "success" => 2,
                "failure" => 1,
                "canonical_ids" => 1,
                "results" => array(
                    array('message_id' => 16),
                    array('error' => 'DOH!'),
                    array('message_id' => 23, 'registration_id' => 42)
                )
            ));

        $this->setResponseExpectations(200, $json);
        $registrationIds = array("4", "8", "15");
        $multicastResponse = $this->sender->sendNoRetry(
            $this->message,
            $registrationIds
        );
        $this->assertNotNull($multicastResponse);
        $this->assertEquals(3, $multicastResponse->getTotal());
        $this->assertEquals(2, $multicastResponse->getSuccess());
        $this->assertEquals(1, $multicastResponse->getFailure());
        $this->assertEquals(1, $multicastResponse->getCanonicalIds());
        $this->assertEquals(108, $multicastResponse->getMulticastId());
        $results = $multicastResponse->getResults();
        $this->assertNotNull($results);
        $this->assertEquals(3, count($results));
        $this->assertResult($results[0], "16", null, null);
        $this->assertResult($results[1], null, 'DOH!', null);
        $this->assertResult($results[2], "23", null, "42");
        $this->assertRequestJsonBody($registrationIds);
    }

    private function assertRequestBody()
    {
        $body = $this->mockClient->requestBody;
        $params = array();
        foreach (explode('&', $body) as $param) {
            list($key, $value) = explode('=', $param, 2);
            $params[$key] = $value;
        }

        $this->assertEquals(7, count($params), $body);
        $this->assertParameter($params, 'registration_id', $this->regId);
        $this->assertParameter($params, 'collapse_key', $this->collapseKey);
        $this->assertParameter($params, 'delay_while_idle', $this->delayWhileIdle ? "1" : "0");
        $this->assertParameter($params, 'time_to_live', "" . $this->ttl);
        $this->assertParameter($params, 'data.k1', 'v1');
        $this->assertParameter($params, 'data.k2', 'v2');
        $this->assertParameter($params, 'data.k3', 'v3');
    }

    private function assertParameter($params, $name, $expectedValue)
    {
        $this->assertEquals(
            $expectedValue,
            $params[$name],
            "invalid value for request parameter parameter " . $name
        );
    }

    private function assertRequestJsonBody(array $expectedRegIds)
    {
        $this->assertEquals(
            Constants::GCM_SEND_ENDPOINT,
            $this->mockClient->url
        );
        $this->assertEquals('application/json', $this->mockClient->mimeType);
        $body = $this->mockClient->requestBody;
        $json = json_decode($body, true);
        $this->assertEquals($this->ttl, $json['time_to_live']);
        $this->assertEquals($this->collapseKey, $json['collapse_key']);
        $this->assertEquals($this->delayWhileIdle, $json['delay_while_idle']);
        $payload = $json['data'];
        $this->assertNotNull($payload, 'no payload');
        $this->assertEquals(3, count($payload), 'wrong payload size');
        $this->assertEquals('v1', $payload['k1']);
        $this->assertEquals('v2', $payload['k2']);
        $this->assertEquals('v3', $payload['k3']);
        $actualRegIds = $json['registration_ids'];
        $this->assertEquals(count($expectedRegIds), count($actualRegIds));
        $this->assertEquals($expectedRegIds, $actualRegIds);
    }

    private function assertResult(
        Result $result,
        $messageId,
        $error,
        $canonicalRegistrationId
    ) {
        $this->assertEquals($messageId, $result->getMessageId());
        $this->assertEquals($error, $result->getErrorCodeName());
        $this->assertEquals(
            $canonicalRegistrationId,
            $result->getCanonicalRegistrationId()
        );
    }
}