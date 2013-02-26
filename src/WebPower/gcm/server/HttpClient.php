<?php
namespace WebPower\gcm\server;

interface HttpClient
{
    /**
     * @param string $apiKey
     * @param string $url
     * @param string $mimeType
     * @param string $requestBody
     * @return HttpClientResponse
     */
    public function post($apiKey, $url, $mimeType, $requestBody);
}