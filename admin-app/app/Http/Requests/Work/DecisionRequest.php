<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission is checked by ScheduleChangeService::decide.
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'decision_note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['decision_note.required_if' => 'Tulis alasan penolakan.'];
    }
}
