<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $supportingFileCount = count($this->file('supporting_files', []));
            foreach (['supporting_labels', 'supporting_party_visible'] as $field) {
                if (count($this->input($field, [])) > $supportingFileCount) {
                    $validator->errors()->add($field, 'Supporting file metadata must match an uploaded file.');
                }
            }
        }];
    }
}
