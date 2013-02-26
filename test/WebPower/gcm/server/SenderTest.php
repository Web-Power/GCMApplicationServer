<?php
namespace WebPower\gcm\server;

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
    private $regId = "15;16";
    private $collapseKey = "collapseKey";
    private $delayWhileIdle = true;
    private $ttl = 108;
    private $authKey = "4815162342";

    /** @var Message */
    private $message;

    /** @var MockHttpClient */
    private $mockClient;

    /** @var Sender */
    private $sender;

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
        $this->sender = new Sender($this->authKey);
        $this->sender->setHttpClient($this->mockClient);
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

    public function testSendNoRetry_ok()
    {
        $this->setResponseExpectations(200, "id=4815162342");
        $result = $this->sender->singleSendNoRetry($this->message, $this->regId);
        $this->assertNotNull($result);
        $this->assertEquals("4815162342", $result->getMessageId());
        $this->assertNull($result->getCanonicalRegistrationId());
        $this->assertNull($result->getErrorCodeName());
        $this->assertRequestBody();
    }

    public function testSendNoRetry_ok_canonical()
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

    public function testSendNoRetry_unauthorized()
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