<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Reviewer permission is checked by TaskWorkflowService::decide inside the transaction.
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'revision'])], 'review_note' => ['nullable', 'required_if:decision,revision', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['review_note.required_if' => 'Tulis alasan revisi supaya pelaksana tahu yang perlu diperbaiki.'];
    }
}
