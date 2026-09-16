<?php

namespace App\Http\Requests\Organization;

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canManageActiveOrganization() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'sometimes',
                'required',
                Rule::in([
                    OrganizationMemberRole::ADMIN->value,
                    OrganizationMemberRole::MANAGER->value,
                    OrganizationMemberRole::AGENT->value,
                    OrganizationMemberRole::TENANT->value,
                ]),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::enum(OrganizationMemberStatus::class),
            ],
        ];
    }
}
