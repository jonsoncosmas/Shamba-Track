<?php

namespace ShambaTrack\Controllers;

use ShambaTrack\Core\Auth;
use ShambaTrack\Core\Http\Request;
use ShambaTrack\Core\Http\Response;
use ShambaTrack\Core\Sms\SmsFactory;
use ShambaTrack\Core\Validation;
use ShambaTrack\Models\Farm;
use ShambaTrack\Models\OtpCode;
use ShambaTrack\Models\User;

class AuthController
{
    public static function sendOtp(): void
    {
        $phoneRaw = (string) Request::input('phone_number', '');
        $phone = Validation::normalizePhone($phoneRaw);

        if (!$phone) {
            Response::error('Namba ya simu si sahihi. / Invalid phone number.', 422);
        }

        $code = OtpCode::generate($phone);
        $message = "Nambari yako ya Shamba Track ni {$code}. Inaisha muda baada ya dakika " . OTP_TTL_MINUTES . ".";

        $sms = SmsFactory::make();
        $sent = $sms->send($phone, $message);

        if (!$sent) {
            Response::error('Imeshindikana kutuma OTP. Jaribu tena. / Failed to send OTP. Try again.', 502);
        }

        $payload = ['success' => true, 'phone_number' => $phone];

        // Dev convenience only: never leak the code in production.
        if (APP_ENV === 'development') {
            $payload['debug_code'] = $code;
        }

        Response::json($payload);
    }

    public static function verifyOtp(): void
    {
        $phoneRaw = (string) Request::input('phone_number', '');
        $code = (string) Request::input('code', '');
        $phone = Validation::normalizePhone($phoneRaw);

        if (!$phone || $code === '') {
            Response::error('Taarifa hazijakamilika. / Missing phone number or code.', 422);
        }

        if (!OtpCode::verify($phone, $code)) {
            Response::error('Nambari ya OTP si sahihi au imeisha muda. / Invalid or expired code.', 401);
        }

        [$user, $wasCreated] = User::findOrCreate($phone);

        $rawToken = \ShambaTrack\Models\AuthSession::create($user['id']);
        Auth::setSessionCookie($rawToken);

        $farm = Farm::findByOwner($user['id']);

        Response::json([
            'success'    => true,
            'is_new_user'=> $wasCreated,
            'user'       => [
                'id'                 => $user['id'],
                'phone_number'       => $user['phone_number'],
                'full_name'          => $user['full_name'],
                'preferred_language' => $user['preferred_language'],
            ],
            'has_farm'   => (bool) $farm,
            'farm'       => $farm,
        ]);
    }

    /** Called on app load to silently check whether the device is already logged in. */
    public static function me(): void
    {
        $user = Auth::currentUser();

        if (!$user) {
            Response::json(['authenticated' => false]);
        }

        $farm = Farm::findByOwner($user['user_id']);

        Response::json([
            'authenticated' => true,
            'user' => [
                'id'                 => $user['user_id'],
                'phone_number'       => $user['phone_number'],
                'full_name'          => $user['full_name'],
                'preferred_language' => $user['preferred_language'],
            ],
            'has_farm' => (bool) $farm,
            'farm'     => $farm,
        ]);
    }

    public static function logout(): void
    {
        $token = Auth::rawTokenFromCookie();
        if ($token) {
            \ShambaTrack\Models\AuthSession::revokeByToken($token);
        }
        Auth::clearSessionCookie();
        Response::json(['success' => true]);
    }
}
