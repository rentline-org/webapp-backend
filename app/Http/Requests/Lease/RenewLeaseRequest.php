<?php

namespace App\Http\Requests\Lease;

use App\Enums\LeaseFinancialTermCalculation;
use App\Enums\LeaseFinancialTermFrequency;
use App\Enums\LeaseFinancialTermType;
use App\Enums\LeasePartyRole;
use App\Models\Lease;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewLeaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lease = $this->route('lease');

        return $lease instanceof Lease && $this->user()?->can('update', $lease) === true;
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
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'financial_terms.*.due_day' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
            'financial_terms.*.effective_from' => ['sometimes', 'nullable', 'date'],
            'financial_terms.*.effective_to' => ['sometimes', 'nullable', 'date'],
            'financial_terms.*.is_liability' => ['sometimes', 'boolean'],
        ];
    }
}
