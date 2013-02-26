<?php
namespace WebPower\gcm\server;

use Buzz\Browser;

class BuzzHttpClientTest extends \PHPUnit_Framework_TestCase
{
    /** @var BuzzHttpClient */
    private $client;
    /** @var \Buzz\Browser|\PHPUnit_Framework_MockObject_MockObject */
    private $browser;
    private $apiKey = 'apiKey';
    private $url = 'http://example.com';
    private $data = array('registration_id' => 'regid', 'message' => 'hoi');
    public static $staticUrl;
    public static $headers;
    public static $requestBody;

    protected function setUp()
    {
        $this->client = new BuzzHttpClient();
        $this->browser = $this->getMockBuilder(
            'Buzz\Browser'
        )->disableOriginalConstructor()->getMock();
        $this->client->setBrowser($this->browser);

        self::$staticUrl = null;
        self::$headers = null;
        self::$requestBody = null;
    }

    public function testCreatesBuzzBrowserWhenNotSet()
    {
        $client = new BuzzHttpClient();
        $browser = $client->getBrowser();

        $this->assertTrue($browser instanceof Browser);
    }

    public function testPost_plaintext()
    {
        $this->setResponse(200, 'plain text');

        $response = $this->client->post(
            $this->apiKey,
            $this->url,
            'text/plain',
            http_build_query($this->data)
        );

        $this->assertTrue($response instanceof HttpClientResponse);
        $this->assertEquals(200, $response->getResponseCode());
        $this->assertEquals('plain text', $response->getContent());

        $this->assertEquals($this->url, self::$staticUrl);
        $this->assertEquals(
            array(
                'Authorization' => 'key=apiKey',
                'Content-Type' => 'text/plain',
                'Accept' => 'text/plain'
            ),
            self::$headers
        );

        $this->assertEquals($this->data, self::$requestBody);
    }

    public function testPost_json()
    {
        $this->setResponse(500, '{"status":"error"}');

        $response = $this->client->post(
            $this->apiKey,
            $this->url,
            'application/json',
            http_build_query($this->data)
        );

        $this->assertTrue($response instanceof HttpClientResponse);
        $this->assertEquals(500, $response->getResponseCode());
        $this->assertEquals('{"status":"error"}', $response->getContent());

        $this->assertEquals($this->url, self::$staticUrl);
        $this->assertEquals(
            array(
                'Authorization' => 'key=apiKey',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            self::$headers
        );

        $this->assertEquals(http_build_query($this->data), self::$requestBody);
    }

    private function setResponse($status, $content)
    {
        $response = $this->getMock('Buzz\Message\Response');
        $response->expects($this->any())->method('getStatusCode')->will(
            $this->returnValue($status)
        );
        $response->expects($this->any())->method('getContent')->will(
            $this->returnValue($content)
        );
        $this->browser->expects($this->any())
            ->method('post')
            ->will($this->returnCallback(function($url, $headers, $requestBody) use($response) {
                        BuzzHttpClientTest::$staticUrl = $url;
                        BuzzHttpClientTest::$headers = $headers;
                        BuzzHttpClientTest::$requestBody = $requestBody;
                        return $response;
                    }));
    }
}