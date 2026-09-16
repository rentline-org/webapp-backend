<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && ($this->user()?->can('update', $document) ?? false);
    }

    public function rules(): array
    {
        $fileRules = [
            'file',
            'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp',
            'extensions:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp',
            'max:10240',
        ];

        return [
            'file' => ['required', ...$fileRules],
            'signed_file' => ['sometimes', ...$fileRules],
            'supporting_files' => ['sometimes', 'array', 'max:20'],
            'supporting_files.*' => $fileRules,
            'supporting_labels' => ['sometimes', 'array', 'max:20'],
            'supporting_labels.*' => ['nullable', 'string', 'max:255'],
            'supporting_party_visible' => ['sometimes', 'array', 'max:20'],
            'supporting_party_visible.*' => ['boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
