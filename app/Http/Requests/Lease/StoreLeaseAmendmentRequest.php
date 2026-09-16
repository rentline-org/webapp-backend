<?php

namespace App\Http\Requests\Lease;

use App\Enums\LeaseFinancialTermCalculation;
use App\Enums\LeaseFinancialTermFrequency;
use App\Enums\LeaseFinancialTermType;
use App\Models\Lease;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseAmendmentRequest extends FormRequest
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
        return [
            'effective_on' => ['required', 'date'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'financial_terms' => ['required', 'array', 'min:1', 'max:25'],
            'financial_terms.*.type' => ['required', Rule::enum(LeaseFinancialTermType::class)],
            'financial_terms.*.calculation' => ['sometimes', Rule::enum(LeaseFinancialTermCalculation::class)],
            'financial_terms.*.amount' => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'financial_terms.*.percentage' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'lte:100'],
            'financial_terms.*.currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'financial_terms.*.frequency' => ['sometimes', Rule::enum(LeaseFinancialTermFrequency::class)],
            'financial_terms.*.calculation_basis' => ['sometimes', 'nullable', 'string', 'max:100'],
            'financial_terms.*.due_day' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
            'financial_terms.*.effective_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_on'],
            'financial_terms.*.is_liability' => ['sometimes', 'boolean'],
        ];
    }
}
