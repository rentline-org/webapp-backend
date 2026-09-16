<?php

namespace App\Http\Requests\Contact;

use App\Enums\ContactAssignmentRole;
use App\Models\Contact;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact && $this->user()?->can('update', $contact) === true;
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
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where(fn ($query) => $query->where('organization_id', $organizationId))],
            'unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('units', 'id')],
            'role' => ['required', Rule::enum(ContactAssignmentRole::class)],
            'is_primary' => ['sometimes', 'boolean'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'],
            'ownership_percentage' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'lte:100'],
        ];
    }
}
