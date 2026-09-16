<?php

namespace App\Http\Requests\Lease;

use App\Models\Lease;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TerminateLeaseRequest extends FormRequest
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
            'terminated_on' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
