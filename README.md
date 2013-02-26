GCMApplicationServer
====================

This is a port to PHP of the official [com.google.android.gcm.server JAVA package](http://developer.android.com/reference/com/google/android/gcm/server/package-summary.html).

Usage
-----
````php
$sender = new \WebPower\gcm\server\Sender('your google cloud messaging api key');
$message = \WebPower\gcm\server\Message::builder()
    ->addData('message', 'Hoi')
    ->build();
// Send to a single device using text/plain
$result = $sender->singleSendNoRetry($message, 'registration id');
// or to multiple devices using application/json
$result = $sender->sendNoRetry($message, array('registration id', 'another registration id'));
echo $result; // all value objects support __toString just like the Java code
````
The Api key can be generated at the [apis console](https://code.google.com/apis/console/)

Registration ids are obtained by the Android App when it registers itself on the GCM service. It should be forwarded to your PHP code.

Installation
------------
````
composer.phar require webpower/gcm-application-server
````