<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('task'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(array_keys(Kode::TASK_STATUS))], 'catatan' => ['nullable', 'string', 'max:2000']];
    }
}
