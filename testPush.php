<?php
include 'vendor/autoload.php';

class EchoLogger extends \Psr\Log\AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        echo sprintf('%s: %s'.PHP_EOL, $level, $message);
    }
}

$sender = new \WebPower\gcm\server\Sender('api key');
$sender->setLogger(new EchoLogger());

$message = \WebPower\gcm\server\Message::builder();
$message->addData('message', 'Hoi');

$result = $sender->singleSendNoRetry($message->build(), 'reg Id');

echo $result;