<?php

namespace ShambaTrack\Core\Sms;

class SmsFactory
{
    public static function make(): SmsProviderInterface
    {
        switch (SMS_PROVIDER) {
            case 'stub':
                return new StubSmsProvider();

            // case 'africastalking':
            //     return new AfricasTalkingProvider(SMS_API_KEY, SMS_SENDER_ID);

            default:
                error_log("Unknown SMS_PROVIDER '" . SMS_PROVIDER . "', falling back to stub.");
                return new StubSmsProvider();
        }
    }
}
