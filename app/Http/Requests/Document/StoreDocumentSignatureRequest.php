<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            && ($this->user()?->can('update', $document) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'signed_file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
                'extensions:pdf,doc,docx,jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }
}
