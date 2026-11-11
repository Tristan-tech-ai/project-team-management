<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin diperiksa ScheduleChangeService::putuskan.
    }

    public function rules(): array
    {
        return ['keputusan' => ['required', Rule::in(['approved', 'rejected'])], 'decision_note' => ['nullable', 'required_if:keputusan,rejected', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['decision_note.required_if' => 'Tulis alasan penolakan.'];
    }
}
