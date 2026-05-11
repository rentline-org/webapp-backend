<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationNotificationController extends Controller
{
    /** Send a new email verification notification. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->respond(null, Response::HTTP_NO_CONTENT);
        }

        // $request->user()->sendEmailVerificationNotification();

        return $this->respond(['status' => 'otp code sent']);
    }
}
