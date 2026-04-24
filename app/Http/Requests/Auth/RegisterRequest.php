<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /** Determine if the user is authorized to make this request. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'role' => 'nullable|in:landlord,tenant',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'device' => 'required|string|max:100',
        ];
    }

    public function bodyParameters()
    {
        return [
            'firstName' => [
                'description' => 'User first name',
                'example' => 'John',
            ],
            'lastName' => [
                'description' => 'User last name',
                'example' => 'Doe',
            ],
            'role' => [
                'description' => 'User role (landlord or tenant)',
                'example' => 'tenant',
            ],
            'email' => [
                'description' => 'User email address',
                'example' => 'superadmin@ims.com',
            ],
            'password' => [
                'description' => 'User password (minimum 6 characters)',
                'example' => '123456',
            ],
            'device' => [
                'description' => 'Device identifier for login tracking',
                'example' => 'mobile_app',
            ],
        ];
    }
}
