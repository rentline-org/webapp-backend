<?php

namespace App\Http\Requests\Contact;

use App\Enums\ContactIdentityKind;
use App\Enums\ContactPersonType;
use App\Enums\ContactTaxIdType;
use App\Models\Contact;
use App\Rules\BrazilianTaxIdentifier;
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
            'identity_kind' => ['sometimes', Rule::enum(ContactIdentityKind::class)],
            'preferred_locale' => ['sometimes', Rule::in(['en', 'pt-BR'])],
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where(
                    fn ($query) => $query->where('organization_id', app(ActiveOrganizationContext::class)->id())
                ),
                Rule::unique('contacts', 'user_id')
                    ->where(fn ($query) => $query->where('organization_id', app(ActiveOrganizationContext::class)->id()))
                    ->ignore($this->route('contact')),
            ],
            'tax_id_type' => ['sometimes', 'nullable', Rule::enum(ContactTaxIdType::class), 'required_with:tax_id'],
            'tax_id' => ['sometimes', 'nullable', 'string', new BrazilianTaxIdentifier($this->input('tax_id_type'))],
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

        foreach (['name', 'email', 'phone', 'tax_id'] as $field) {
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
