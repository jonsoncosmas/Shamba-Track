<?php

namespace ShambaTrack\Core\Sms;

interface SmsProviderInterface
{
    /**
     * Send an SMS. Returns true on accepted-for-delivery, false on failure.
     * Implementations must never throw for expected failures — return false
     * and let the caller decide how to surface it.
     */
    public function send(string $phoneNumber, string $message): bool;
}
