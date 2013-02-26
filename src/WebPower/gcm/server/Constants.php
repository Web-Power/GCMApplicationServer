<?php
namespace WebPower\gcm\server;

class Constants
{
    /** @var string Too many messages sent by the sender to a specific device. Retry after a while. */
    const ERROR_DEVICE_QUOTA_EXCEEDED = "DeviceQuotaExceeded";

    /** @var string A particular message could not be sent because the GCM servers encountered an error. Used only on JSON requests, as in plain text requests internal errors are indicated by a 500 response. */
    const ERROR_INTERNAL_SERVER_ERROR = "InternalServerError";

    /** @var string Bad registration_id. Sender should remove this registration_id. */
    const ERROR_INVALID_REGISTRATION = "InvalidRegistration";

    /** @var string Time to Live value passed is less than zero or more than maximum. */
    const ERROR_INVALID_TTL = "InvalidTtl";

    /** @var string The payload of the message is too big, see the limitations. Reduce the size of the message. */
    const ERROR_MESSAGE_TOO_BIG = "MessageTooBig";

    /** @var string The sender_id contained in the registration_id does not match the sender_id used to register with the GCM servers. */
    const ERROR_MISMATCH_SENDER_ID = "MismatchSenderId";

    /** @var string Collapse key is required. Include collapse key in the request. */
    const ERROR_MISSING_COLLAPSE_KEY = "MissingCollapseKey";

    /** @var string Missing registration_id. Sender should always add the registration_id to the request. */
    const ERROR_MISSING_REGISTRATION = "MissingRegistration";

    /** @var string The user has uninstalled the application or turned off notifications. Sender should stop sending messages to this device and delete the registration_id. The client needs to re-register with the GCM servers to receive notifications again. */
    const ERROR_NOT_REGISTERED = "NotRegistered";

    /** @var string Too many messages sent by the sender. Retry after a while. */
    const ERROR_QUOTA_EXCEEDED = "QuotaExceeded";

    /** @var string A particular message could not be sent because the GCM servers were not available. Used only on JSON requests, as in plain text requests unavailability is indicated by a 503 response. */
    const ERROR_UNAVAILABLE = "Unavailable";

    /** @var string Endpoint for sending messages. */
    const GCM_SEND_ENDPOINT = "https://android.googleapis.com/gcm/send";

    /** @var string JSON-only field representing the number of messages with a canonical registration id. */
    const JSON_CANONICAL_IDS = "canonical_ids";

    /** @var string JSON-only field representing the error field of an individual request. */
    const JSON_ERROR = "error";

    /** @var string JSON-only field representing the number of failed messages. */
    const JSON_FAILURE = "failure";

    /** @var string JSON-only field sent by GCM when a message was successfully sent. */
    const JSON_MESSAGE_ID = "message_id";

    /** @var string JSON-only field representing the id of the multicast request. */
    const JSON_MULTICAST_ID = "multicast_id";

    /** @var string JSON-only field representing the payload data. */
    const JSON_PAYLOAD = "data";

    /** @var string JSON-only field representing the registration ids. */
    const JSON_REGISTRATION_IDS = "registration_ids";

    /** @var string JSON-only field representing the result of each individual request. */
    const JSON_RESULTS = "results";

    /** @var string JSON-only field representing the number of successful messages. */
    const JSON_SUCCESS = "success";

    /** @var string HTTP parameter for collapse key. */
    const PARAM_COLLAPSE_KEY = "collapse_key";

    /** @var string HTTP parameter for delaying the message delivery if the device is idle. */
    const PARAM_DELAY_WHILE_IDLE = "delay_while_idle";

    /** @var string Prefix to HTTP parameter used to pass key-values in the message payload. */
    const PARAM_PAYLOAD_PREFIX = "data.";

    /** @var string HTTP parameter for registration id. */
    const PARAM_REGISTRATION_ID = "registration_id";

    /** @var string Prefix to HTTP parameter used to set the message time-to-live. */
    const PARAM_TIME_TO_LIVE = "time_to_live";

    /** @var string Token returned by GCM when the requested registration id has a canonical value. */
    const TOKEN_CANONICAL_REG_ID = "registration_id";

    /** @var string Token returned by GCM when there was an error sending a message. */
    const TOKEN_ERROR = "Error";

    /** @var string Token returned by GCM when a message was successfully sent. */
    const TOKEN_MESSAGE_ID = "id";
}