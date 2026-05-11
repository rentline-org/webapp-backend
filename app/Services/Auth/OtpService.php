<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class OtpService
{
    private const USER_OTP_PREFIX = 'OTP_';
    private const OTP_RESEND_PREFIX = 'OTP_RESEND_';

    public function issueOtp(User $user, ?int $expiryMinutes = null): string
    {
        $expiryMinutes ??= config('auth.login.otp.expiry_minutes', 5);

        $this->clearOtp($user);

        $code = $this->generateOtpCode();

        Cache::put(
            $this->cacheKey($user),
            $code,
            now()->addMinutes($expiryMinutes)
        );

        $this->markSentAt($user);

        return $code;
    }

    public function verifyOtp(User $user, string $code): bool
    {

        if ($this->isOtpExpired($user)) {
            return false;
        }

        $cachedCode = $this->getCachedOtp($user);

        if ($cachedCode === null) {
            return false;
        }

        return hash_equals($cachedCode, $this->normalizeOtp($code));
    }

    public function isOtpExpired(User $user): bool
    {
        return $this->getCachedOtp($user) === null;
    }

    public function clearOtp(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    private function ensureCanResend(User $user): void
    {
        $cooldownSeconds = 60;
        $lastSentAt = Cache::get($this->resendKey($user));

        if (! $lastSentAt) {
            return;
        }

        if (now()->diffInSeconds(Carbon::parse($lastSentAt)) < $cooldownSeconds) {
            throw ValidationException::withMessages([
                'otp' => 'OTP code expired',
            ]);
        }
    }

    private function generateOtpCode(): string
    {
        $length = (int) config('auth.login.otp.length', 6);

        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;

        return (string) random_int($min, $max);
    }

    private function getCachedOtp(User $user): ?string
    {
        $value = Cache::get($this->cacheKey($user));

        return $value !== null ? (string) $value : null;
    }

    private function cacheKey(User $user): string
    {
        return self::USER_OTP_PREFIX . $user->id;
    }

    private function normalizeOtp(string $code): string
    {
        return trim($code);
    }

    private function resendKey(User $user): string
    {
        return self::OTP_RESEND_PREFIX . $user->id;
    }

    private function markSentAt(User $user): void
    {
        $cooldownSeconds = (int) config('auth.login.otp.resend_cooldown_seconds', 60);

        Cache::put(
            $this->resendKey($user),
            now()->toIso8601String(),
            now()->addSeconds($cooldownSeconds)
        );
    }
}
