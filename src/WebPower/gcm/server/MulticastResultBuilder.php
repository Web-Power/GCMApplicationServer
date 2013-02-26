<?php
namespace WebPower\gcm\server;

class MulticastResultBuilder
{
    private $success;
    private $failure;
    private $canonicalIds;
    private $multicastId;
    private $retryMulticastIds;
    private $results;

    public function __construct($success, $failure, $canonicalIds, $multicastId)
    {
        $this->success = $success;
        $this->failure = $failure;
        $this->canonicalIds = $canonicalIds;
        $this->multicastId = $multicastId;
        $this->results = array();
        $this->retryMulticastIds = array();
    }

    /**
     * @param $multicastIds
     * @return MulticastResultBuilder
     */
    public function retryMulticastIds($multicastIds)
    {
        $this->retryMulticastIds = $multicastIds;
        return $this;
    }

    /**
     * @param Result $result
     * @return MulticastResultBuilder
     */
    public function addResult(Result $result)
    {
        $this->results[] = $result;
        return $this;
    }

    public function build()
    {
        return new MulticastResult($this->canonicalIds, $this->failure, $this->multicastId, $this->results, $this->retryMulticastIds, $this->success);
    }
}