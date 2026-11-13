<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255']];
    }
}
