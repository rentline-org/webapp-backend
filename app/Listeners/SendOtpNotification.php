<?php

namespace App\Listeners;

use App\Events\OtpRequested;
use App\Notifications\OtpCodeNotification;
use App\Services\Auth\OtpService;

class SendOtpNotification
{
    /** Create the event listener. */
    public function __construct(protected OtpService $otpService) {}

    /** Handle the event. */
    public function handle(OtpRequested $event): void
    {
        $otp = $this->otpService->issueOtp($event->user, config('auth.login.otp.expiry_minutes', 5));
        $event->user->notify(new OtpCodeNotification($otp, $event->purpose));
    }
}
