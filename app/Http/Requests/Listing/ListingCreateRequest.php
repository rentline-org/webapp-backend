<?php

namespace App\Http\Requests\Listing;

use App\Enums\ListingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingCreateRequest extends FormRequest
{
    /** Determine if the user is authorized to make this request. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::enum(ListingType::class),

                Rule::unique('listing', 'type')
                    ->where(fn ($query) => $query->where(
                        'organization_id',
                        $this->input('organization_id')
                    )),
            ],
        ];
    }
}
