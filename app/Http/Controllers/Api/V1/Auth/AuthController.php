<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\User\UserResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Authentication
 *
 * APIs for user authentication, login, and logout
 */
class AuthController extends Controller
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct(
        protected AuthService $authService,
    ) {}

    public function login(LoginRequest $request)
    {
        $user = User::select([
            'id',
            'name',
            'email',
            'email_verified_at',
            'phone',
            'password',
            'is_active',
            'created_at',
            'updated_at',
        ])
            ->where('email', $request->email)
            ->with('roles', 'media')
            ->first();

        $authData = $this->authService->login(
            $user,
            $request->password,
            $request->device,
        );

        $data = [
            'user' => UserResource::make($authData['user']),
            'status' => $authData['status'],
            'message' => $authData['message'],
            'token' => $authData['token'],
        ];

        return $this->respond($data, Response::HTTP_OK);
    }

    public function register(RegisterRequest $request)
    {
        $request->validated();

        $user = $this->authService->createAccount($request);

        return $this->respond($user, Response::HTTP_CREATED);
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        $authData = $this->authService->verifyOtpAndLogin(
            $user,
            $request->otp,
            $request->device
        );

        $data = [
            'user' => UserResource::make($authData['user']),
            'status' => $authData['status'],
            'message' => $authData['message'],
            'token' => $authData['token'],
        ];

        return $this->respond($data, Response::HTTP_OK);
    }

    /**
     * User logout
     *
     * Logout user from specific device and revoke access token.
     */
    public function logout(LogoutRequest $request)
    {
        $this->authService->logout($request->device);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /*
        Select Organization after login is completed and token has been generated. This will set the organization_id in the personal access token for multi-tenancy support.
    */
    public function selectOrganization(Request $request, Organization $organization)
    {
        $user = $request->user();

        $this->authService->selectOrganization($user, $organization->id);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    // forgot password flow
    public function forgotPassword(Request $request)
    {
        $user = $request->user();
        // send password reset link via email
    }
}
