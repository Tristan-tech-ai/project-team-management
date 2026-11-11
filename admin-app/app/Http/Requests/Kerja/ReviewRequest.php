<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izin reviewer diperiksa TaskWorkflowService::putuskan di dalam transaksi.
    }

    public function rules(): array
    {
        return ['keputusan' => ['required', Rule::in(['approved', 'revision'])], 'review_note' => ['nullable', 'required_if:keputusan,revision', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['review_note.required_if' => 'Tulis alasan revisi supaya pelaksana tahu yang perlu diperbaiki.'];
    }
}
