<?php

namespace App\Http\Requests\Organization;

use App\Enums\OrganizationMemberRole;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationInvitationRequest extends FormRequest
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
        $organizationId = app(ActiveOrganizationContext::class)->id();

        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => [
                'required',
                Rule::in([
                    OrganizationMemberRole::ADMIN->value,
                    OrganizationMemberRole::MANAGER->value,
                    OrganizationMemberRole::AGENT->value,
                    OrganizationMemberRole::TENANT->value,
                ]),
            ],
            'locale' => ['sometimes', 'required', Rule::in(config('app.supported_locales', ['en']))],
            'contact_id' => [
                Rule::requiredIf($this->input('role') === OrganizationMemberRole::TENANT->value),
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'locale' => $this->input('locale', 'en'),
        ]);
    }
}
