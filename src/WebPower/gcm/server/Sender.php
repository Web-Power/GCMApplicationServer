<?php
namespace WebPower\gcm\server;

/**
 * Helper class to send messages to the GCM service using an API Key.
 */
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class Sender implements LoggerAwareInterface
{
    /** @var int Initial delay before first retry, without jitter. */
    const BACKOFF_INITIAL_DELAY = 1000;
    /** @var int Maximum delay before a retry. */
    const MAX_BACKOFF_DELAY = 1024000;
    /** @var string */
    const UTF8 = "UTF-8";

    private $key;
    /** @var LoggerInterface */
    private $logger;
    /** @var HttpClient */
    private $httpClient;

    public function __construct($key)
    {
        if (!$key) {
            throw new \InvalidArgumentException();
        }
        $this->key = $key;
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    private function getHttpClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = new BuzzHttpClient();
        }
        return $this->httpClient;
    }

    /**
     * Sends a message to many devices, retrying in case of unavailability.
     *
     * Note: this mesthod uses exponential back-off to retry in case of service
     * unavailability and hence could block the process for many seconds.
     *
     * @param Message $message message to be sent.
     * @param array $regIds registration id of the devices that will receive the message.
     * @param $retries number of retries in case of service unavailability errors.
     * @return MulticastResult combined result of all requests made.
     *
     * @throws \InvalidArgumentException if registrationIds is null or empty.
     * @throws InvalidRequestException if GCM didn't returned a 200 or 503 status.
     * @throws \RuntimeException if message could not be sent.
     */
    public function send(Message $message, array $regIds, $retries = null)
    {
        $attempt = 0;
        $multicastResult = null;
        $backOff = self::BACKOFF_INITIAL_DELAY;
        // Map of results by registration id, it will be updated after each attempt
        // to send the messages
        /** @var $results Result[] */
        $results = array();
        $unsentRegIds = array();
        $multicastIds = array();

        do {
            $attempt += 1;
            if ($this->logger) {
                $this->logger->info(
                    "Attempt #{attempt} to send message {message} to regIds {unsentRegIds}",
                    array(
                        "attempt" => $attempt,
                        "message" => $message,
                        "unsentRegIds" => $unsentRegIds
                    )
                );
            }
            $multicastResult = $this->sendNoRetry($message, $unsentRegIds);
            $multicastId = $multicastResult->getMulticastId();
            if ($this->logger) {
                $this->logger->info(
                    "multicast_id on attempt #{attempt}: {multicastId}",
                    array("attempt" => $attempt, "multicastId" => $multicastId)
                );
            }
            $multicastIds[] = $multicastId;
            $unsentRegIds = $this->updateStatus(
                $unsentRegIds,
                $results,
                $multicastResult
            );
            $tryAgain = !empty($unsentRegIds) && $attempt <= $retries;
            if ($tryAgain) {
                $sleepTime = $backOff / 2 + mt_rand(0, $backOff);
                $this->sleep($sleepTime);
                if (2 * $backOff < self::MAX_BACKOFF_DELAY) {
                    $backOff *= 2;
                }
            }
        } while ($tryAgain);
        // calculate summary
        $success = 0;
        $failure = 0;
        $canonicalIds = 0;

        foreach ($results as $result) {
            if ($result->getMessageId() !== null) {
                $success += 1;
                if ($result->getCanonicalRegistrationId() !== null) {
                    $canonicalIds += 1;
                }
            } else {
                $failure += 1;
            }
        }
        // build a new object with the overall result
        $multicastId = array_shift($multicastIds);
        $builder = MulticastResult::builder($success, $failure, $canonicalIds, $multicastId)->retryMulticastIds($multicastIds);
        // add results, in the same order as the input
        foreach ($regIds as $regId) {
            $result = $results[$regId];
            $builder->addResult($result);
        }

        return $builder->build();
    }

    /**
     * Sends a message without retrying in case of service unavailability. See
     * send for more info.
     *
     * @param Message $message
     * @param array $registrationIds
     * @throws \RuntimeException When JSON response can't be decoded
     * @throws \InvalidArgumentException when registrationIds is empty
     * @throws InvalidRequestException
     * @return MulticastResult
     */
    public function sendNoRetry(Message $message, array $registrationIds)
    {
        if (!$registrationIds) {
            throw new \InvalidArgumentException("registrationIds cannot be empty");
        }

        $jsonRequest = array(
            Constants::PARAM_TIME_TO_LIVE => $message->getTimeToLive(),
            Constants::PARAM_COLLAPSE_KEY => $message->getCollapseKey(),
            Constants::PARAM_DELAY_WHILE_IDLE => $message->isDelayWhileIdle(),
            Constants::JSON_REGISTRATION_IDS => $registrationIds,
        );
        $payload = $message->getData();
        if (!empty($payload)) {
            $jsonRequest[Constants::JSON_PAYLOAD] = $message->getData();
        }
        $jsonRequest = array_filter($jsonRequest, function($value) { return $value !== null; });
        $requestBody = json_encode($jsonRequest);
        if ($this->logger) {
            $this->logger->debug("JSON request: " . $requestBody);
        }

        $conn = $this->post(
            Constants::GCM_SEND_ENDPOINT,
            "application/json",
            $requestBody
        );
        $status = $conn->getResponseCode();
        if ($status != 200) {
            throw new InvalidRequestException($status, $conn->getContent());
        }
        $responseBody = $conn->getContent();
        if ($this->logger) {
            $this->logger->debug("JSON response: " . $responseBody);
        }

        $jsonResponse = json_decode($responseBody, true);
        try {
            $success = (int) $this->getJsonValue($jsonResponse, Constants::JSON_SUCCESS, true);
            $failure = (int) $this->getJsonValue($jsonResponse, Constants::JSON_FAILURE, true);
            $canonicalIds = (int) $this->getJsonValue($jsonResponse, Constants::JSON_CANONICAL_IDS, true);
            $multicastId = (int) $this->getJsonValue($jsonResponse, Constants::JSON_MULTICAST_ID, true);
            $builder = MulticastResult::builder(
                $success,
                $failure,
                $canonicalIds,
                $multicastId
            );

            $results = $this->getJsonValue($jsonResponse, Constants::JSON_RESULTS);
            if (is_array($results)) {
                foreach ($results as $jsonResult) {
                    $messageId = $this->getJsonValue($jsonResult, Constants::JSON_MESSAGE_ID);
                    $canonicalRegId = $this->getJsonValue($jsonResult, Constants::TOKEN_CANONICAL_REG_ID);
                    $error = $this->getJsonValue($jsonResult, Constants::JSON_ERROR);
                    $result = Result::builder()
                        ->messageId($messageId)
                        ->canonicalRegistrationId($canonicalRegId)
                        ->errorCode($error)
                        ->build();
                    $builder->addResult($result);
                }
            }
        } catch(JsonParserException $e) {
            $msg = "Error parsing JSON response ({$responseBody}):".$e;
            if ($this->logger) {
                $this->logger->warning($msg);
            }
            throw new \RuntimeException($msg);
        }

        $multicastResult = $builder->build();
        return $multicastResult;
    }

    /**
     * Sends a message to one device, retrying in case of unavailability
     *
     * Note: this method uses exponential back-off to retry in case of service
     * unavailability and hence could block for many seconds.
     *
     * @param Message $message message to be sent, including the device's registration id.
     * @param string $registrationId device where the message will be sent.
     * @param int $retries number of retries in case of service unavailability errors.
     *
     * @return Result of the request
     *
     * @throws \InvalidArgumentException if registrationId is null.
     * @throws InvalidRequestException if GCM didn't returned a 200 or 503 status.
     * @throws \RuntimeException if message could not be sent.
     */
    public function singleSend(Message $message, $registrationId, $retries = null)
    {
        $attempt = 0;
        $result = null;
        $backOff = self::BACKOFF_INITIAL_DELAY;

        do {
            $attempt += 1;
            if ($this->logger) {
                $this->logger->info(
                    'Attempt #{attempt} to send message {message} to regIds {registrationId}',
                    array('attempt' => $attempt, 'message' => $message, 'registrationId' => $registrationId)
                );
            }
            $result = $this->singleSendNoRetry($message, $registrationId);
            $tryAgain = $result == null && $attempt <= $retries;
            if ($tryAgain) {
                $sleepTime = $backOff / 2 + mt_rand(0, $backOff);
                $this->sleep($sleepTime);
                if (2 * $backOff < self::MAX_BACKOFF_DELAY) {
                    $backOff *= 2;
                }
            }
        } while ($tryAgain);
        if ($result === null) {
            throw new \RuntimeException('Could not send message after ' . $attempt . 'attempts');
        }
        return $result;
    }


    public function singleSendNoRetry(Message $message, $registrationId)
    {
        if (!$registrationId) {
            throw new \InvalidArgumentException('Registration Id should not be empty');
        }

        $body = array(Constants::PARAM_REGISTRATION_ID => $registrationId);
        $delayWhileIdle = $message->isDelayWhileIdle();
        if ($delayWhileIdle !== null) {
            $body[Constants::PARAM_DELAY_WHILE_IDLE] = $delayWhileIdle ? '1' : '0';
        }
        $collapseKey = $message->getCollapseKey();
        if ($collapseKey !== null) {
            $body[Constants::PARAM_COLLAPSE_KEY] = $collapseKey;
        }
        $timeToLive = $message->getTimeToLive();
        if ($timeToLive !== null) {
            $body[Constants::PARAM_TIME_TO_LIVE] = $timeToLive;
        }
        foreach ($message->getData() as $key => $value) {
            $body[Constants::PARAM_PAYLOAD_PREFIX . $key] = $value;
        }
        $requestBody = urldecode(http_build_query($body));
        if ($this->logger) {
            $this->logger->debug('Request body: ' . $requestBody);
        }
        $conn = $this->post(Constants::GCM_SEND_ENDPOINT, "text/plain", $requestBody);
        $status = $conn->getResponseCode();
        if ($status == 503) {
            if ($this->logger) {
                $this->logger->info('GCM service is unavailable');
            }
            return null;
        }
        if ($status != 200) {
            throw new InvalidRequestException($status);
        }
        $content = $conn->getContent();
        $lines = explode("\n", $content);

        $line = array_shift($lines);

        if ($line === false || $line == "") {
            throw new \RuntimeException("Received empty response from GCM service.");
        }
        list($token, $value) = $this->split($line);

        if ($token == Constants::TOKEN_MESSAGE_ID) {
            $builder = Result::builder()->messageId($value);
            // check for canonical registration id
            $line = array_shift($lines);
            if ($line !== null) {
                list($token, $value) = $this->split($line);
                if ($token == Constants::TOKEN_CANONICAL_REG_ID) {
                    $builder->canonicalRegistrationId($value);
                } else {
                    if ($this->logger) {
                        $this->logger->warning(
                            "Received invalid second line from GCM: " . $line
                        );
                    }
                }
            }

            $result = $builder->build();
            if ($this->logger) {
                $this->logger->info("Message created succesfully ({message})", array("message" => $result));
            }
            return $result;
        } else if ($token == Constants::TOKEN_ERROR) {
            return Result::builder()->errorCode($value)->build();
        } else {
            throw new \RuntimeException("Received invalid response from GCM: " . $line);
        }
    }

    private function sleep($sleepTime)
    {
        usleep($sleepTime * 1000);
    }

    private function split($line)
    {
        $split = explode('=', $line, 2);
        if (count($split) !== 2) {
            throw new \RuntimeException("Received invalid response line from GCM: ".$line);
        }
        return $split;
    }

    private function post($url, $mimeType, $requestBody)
    {
        return $this->getHttpClient()->post($this->key, $url, $mimeType, $requestBody);
    }

    private function updateStatus(array $unsentRegIds, array $allResults, MulticastResult $multicastResult)
    {
        $results = $multicastResult->getResults();
        if (count($results) != count($unsentRegIds)) {
            // should never happen, unless there is a flaw in the algorithm
            throw new \RuntimeException("Internal error: sizes do not match. " .
                "currentResults: {$results}; unsentRegIds: {$unsentRegIds}");
        }
        $newUnsentRegIds = array();
        foreach ($unsentRegIds as $i => $regId) {
            $result = $results[$i];
            $allResults[$regId] = $result;
            $error = $result->getErrorCodeName();
            if ($error !== null && $error == Constants::ERROR_UNAVAILABLE) {
                $newUnsentRegIds[] = $regId;
            }
        }

        return $newUnsentRegIds;
    }

    private function getJsonValue(array $json, $key, $require = false)
    {
        $value = null;
        if (array_key_exists($key, $json)) {
            $value = $json[$key];
        }

        if ($value === null && $require) {
            throw new JsonParserException("Missing field: " . $key);
        }
        return $value;
    }
}