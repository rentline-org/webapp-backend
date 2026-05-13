<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ThumbnailInsertRequest extends FormRequest
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
            'thumbnail' => ['required', 'file', 'image', 'max:2048'],
        ];
    }
}
