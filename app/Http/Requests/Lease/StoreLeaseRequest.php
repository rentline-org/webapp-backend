<?php

namespace App\Http\Requests\Lease;

use App\Enums\LeaseFinancialTermCalculation;
use App\Enums\LeaseFinancialTermFrequency;
use App\Enums\LeaseFinancialTermType;
use App\Enums\LeasePartyRole;
use App\Enums\RentalGuaranteeType;
use App\Models\Lease;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lease::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        $contactExists = Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('organization_id', $organizationId));

        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where(fn ($query) => $query->where('organization_id', $organizationId))],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after:starts_on'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'guarantee_type' => ['sometimes', 'nullable', Rule::enum(RentalGuaranteeType::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'parties' => ['sometimes', 'array', 'max:25'],
            'parties.*.contact_id' => ['required', 'integer', $contactExists],
            'parties.*.role' => ['required', Rule::enum(LeasePartyRole::class)],
            'parties.*.is_primary' => ['sometimes', 'boolean'],
            'financial_terms' => ['sometimes', 'array', 'max:25'],
            'financial_terms.*.type' => ['required', Rule::enum(LeaseFinancialTermType::class)],
            'financial_terms.*.calculation' => ['sometimes', Rule::enum(LeaseFinancialTermCalculation::class)],
            'financial_terms.*.amount' => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'financial_terms.*.percentage' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'lte:100'],
            'financial_terms.*.currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'financial_terms.*.frequency' => ['sometimes', Rule::enum(LeaseFinancialTermFrequency::class)],
            'financial_terms.*.calculation_basis' => ['sometimes', 'nullable', 'string', 'max:100'],
            'financial_terms.*.due_day' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
            'financial_terms.*.payer_contact_id' => ['sometimes', 'nullable', 'integer', $contactExists],
            'financial_terms.*.payee_contact_id' => ['sometimes', 'nullable', 'integer', $contactExists],
            'financial_terms.*.effective_from' => ['sometimes', 'nullable', 'date'],
            'financial_terms.*.effective_to' => ['sometimes', 'nullable', 'date'],
            'financial_terms.*.is_liability' => ['sometimes', 'boolean'],
        ];
    }
}
