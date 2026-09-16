<?php

namespace App\Http\Requests\Document;

use App\Enums\DocumentSignerStatus;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && ($this->user()?->can('update', $document) ?? false);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(DocumentSignerStatus::class)]];
    }
}
