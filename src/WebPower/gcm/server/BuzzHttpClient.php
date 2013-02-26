<?php
namespace WebPower\gcm\server;

use Buzz\Browser;
use Buzz\Client\Curl;
use Buzz\Message\Response;

class BuzzHttpClient implements HttpClient
{
    /** @var Browser */
    private $browser;

    /**
     * @param \Buzz\Browser $browser
     * @return BuzzHttpClient self
     */
    public function setBrowser(Browser $browser)
    {
        $this->browser = $browser;
        return $this;
    }

    /**
     * @return \Buzz\Browser
     */
    public function getBrowser()
    {
        if (!$this->browser) {
            $this->browser = new Browser(new Curl());
        }
        return $this->browser;
    }

    /**
     * @param string $apiKey
     * @param string $url
     * @param string $mimeType
     * @param string $requestBody
     * @return HttpClientResponse
     */
    public function post($apiKey, $url, $mimeType, $requestBody)
    {
        $browser = $this->getBrowser();
        $headers = array(
            'Authorization' => 'key='.$apiKey,
            'Content-Type' => $mimeType,
            'Accept' => $mimeType
        );

        if ($mimeType === 'text/plain') {
            parse_str($requestBody, $requestBody);
        }

        /** @var $response Response */
        $response = $browser->post($url, $headers, $requestBody);

        return new HttpClientResponse(
            $response->getStatusCode(),
            $response->getContent()
        );
    }
}