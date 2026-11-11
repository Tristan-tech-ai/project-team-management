<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssigneeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('task'));
    }

    public function rules(): array
    {
        return ['pic_user_id' => ['required', 'integer', 'exists:users,id'], 'assignees' => ['array'], 'assignees.*' => ['integer', 'exists:users,id']];
    }
}
