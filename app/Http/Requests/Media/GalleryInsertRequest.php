<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GalleryInsertRequest extends FormRequest
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
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'image', 'max:2048'],
            'names' => ['nullable', 'array'],
            'names.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
