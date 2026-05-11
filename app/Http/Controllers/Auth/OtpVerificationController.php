<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ApiErrorCode;
use App\Events\OtpRequested;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\User\UserResource;
use App\Services\Auth\AuthService;
use App\Services\Auth\OtpService;
use App\Services\User\UserProfileCacheService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OtpVerificationController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected AuthService $authService
    ) {}

    public function verify(VerifyOtpRequest $request)
    {
        $user = $this->authService->findExistingUserByEmail($request->email);

        if ($user->hasVerifiedEmail()) {
            return $this->respond([
                'message' => 'Already verified',
                'user' => UserResource::make($user),
            ]);
        }

        if (! $this->otpService->verifyOtp($user, $request->otp)) {
            $this->throwOtpError(
                ApiErrorCode::UNAUTHORIZED->value,
                __('messages.login.invalid.otp')
            );
        }

        $this->otpService->clearOtp($user);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        UserProfileCacheService::forget($user->id);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->load(['media']);

        return $this->respond([
            'message' => __('messages.login.success'),
            'user' => UserResource::make($user),
        ]);

    }

    public function resend(ResendOtpRequest $request)
    {
        $request->validated();
        $user = $this->authService->findExistingUserByEmail($request->email);

        if ($user->hasVerifiedEmail()) {
            return $this->respond([
                'message' => 'Already verified',
                'user' => $user,
            ]);
        }

        event(new OtpRequested($user, 'resend-verification'));

        return $this->respond([
            'message' => 'New OTP Sent',
        ]);
    }

    private function throwOtpError(string $code, string $message): never
    {
        throw new ApiException(
            Response::HTTP_BAD_REQUEST,
            $code,
            $message,
            __('messages.login.fail.general')
        );
    }
}
