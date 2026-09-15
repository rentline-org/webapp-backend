<?php

namespace App\Http\Requests\Contact;

use App\Enums\ContactPersonType;
use App\Models\Contact;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactInsertUpdateRequest extends FormRequest
{
    /** Determine if the user is authorized to make this request. */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $contact = $this->route('contact');

        return $contact instanceof Contact
            ? $user->can('update', $contact)
            : $user->can('create', Contact::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'min:1',
                'max:255',
            ],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                Rule::enum(ContactPersonType::class),
            ],
            'property_ids' => ['sometimes', 'array'],
            'property_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('properties', 'id')->where(
                    fn ($query) => $query->where(
                        'organization_id',
                        app(ActiveOrganizationContext::class)->id()
                    )
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['name', 'email', 'phone'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $value = trim($this->input($field));
            $normalized[$field] = $field === 'name' || $value !== ''
                ? $value
                : null;
        }

        $this->merge($normalized);
    }
}
