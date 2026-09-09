<?php

namespace ShambaTrack\Core\Sms;

/**
 * Development/testing provider: does not send a real SMS.
 * Logs the message to the PHP error log so a developer can read the OTP
 * during testing. Swap SMS_PROVIDER in config.php to a real gateway
 * (e.g. Africa's Talking) once one is chosen — no controller code changes.
 */
class StubSmsProvider implements SmsProviderInterface
{
    public function send(string $phoneNumber, string $message): bool
    {
        error_log("[StubSmsProvider] To: {$phoneNumber} | Message: {$message}");
        return true;
    }
}
