<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
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
        return ['status' => ['required', Rule::in(array_keys(Codes::TASK_STATUS))], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
