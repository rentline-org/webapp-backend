<?php

namespace App\Http\Controllers\Auth;

use App\Events\OtpRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /** Handle an incoming authentication request. */
    public function store(LoginRequest $request)
    {
        $user = $request->authenticate();

        if (! $user->hasVerifiedEmail()) {
            event(new OtpRequested($user, 'login'));

            return $this->respond([
                'message' => 'Verify your email',
                'user' => $user,
                'verified' => false,
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->respond([
            'user' => $user,
            'message' => 'Login Success',
            'verified' => true,
        ], Response::HTTP_ACCEPTED);
    }

    /** Destroy an authenticated session. */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
