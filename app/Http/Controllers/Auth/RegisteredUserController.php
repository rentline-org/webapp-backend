<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(RegisterRequest $request)
    {
        $request->validated();
        $result = $this->authService->createAccount($request);

        return $this->respond($result, Response::HTTP_CREATED);
    }
}
