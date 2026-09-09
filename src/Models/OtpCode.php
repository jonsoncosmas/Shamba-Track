<?php

namespace ShambaTrack\Models;

use ShambaTrack\Core\Database;

class OtpCode
{
    private const MAX_ATTEMPTS = 5;

    /** Generate, store (hashed), and return the raw code for the caller to send via SMS. */
    public static function generate(string $phoneNumber): string
    {
        $pdo = Database::connect();

        // Invalidate any still-live codes for this number first.
        $stmt = $pdo->prepare(
            'UPDATE otp_codes SET consumed_at = NOW() WHERE phone_number = :phone AND consumed_at IS NULL'
        );
        $stmt->execute(['phone' => $phoneNumber]);

        $code = self::randomNumericCode(OTP_LENGTH);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + OTP_TTL_MINUTES * 60);

        $stmt = $pdo->prepare(
            'INSERT INTO otp_codes (phone_number, code_hash, expires_at) VALUES (:phone, :hash, :expires)'
        );
        $stmt->execute(['phone' => $phoneNumber, 'hash' => $hash, 'expires' => $expiresAt]);

        return $code;
    }

    /**
     * Verify a submitted code against the latest unconsumed, unexpired OTP.
     * Returns true/false. Increments attempt_count on failure; locks out
     * after MAX_ATTEMPTS until a new code is requested.
     */
    public static function verify(string $phoneNumber, string $submittedCode): bool
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            'SELECT * FROM otp_codes
             WHERE phone_number = :phone AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['phone' => $phoneNumber]);
        $row = $stmt->fetch();

        if (!$row) {
            return false; // no active code — must request a new one
        }

        if ($row['attempt_count'] >= self::MAX_ATTEMPTS) {
            return false; // locked out, must request a new code
        }

        if (strtotime($row['expires_at']) < time()) {
            return false; // expired
        }

        if (!password_verify($submittedCode, $row['code_hash'])) {
            $pdo->prepare('UPDATE otp_codes SET attempt_count = attempt_count + 1 WHERE id = :id')
                ->execute(['id' => $row['id']]);
            return false;
        }

        $pdo->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id')
            ->execute(['id' => $row['id']]);

        return true;
    }

    private static function randomNumericCode(int $length): string
    {
        $max = (10 ** $length) - 1;
        $code = random_int(0, $max);
        return str_pad((string) $code, $length, '0', STR_PAD_LEFT);
    }
}
