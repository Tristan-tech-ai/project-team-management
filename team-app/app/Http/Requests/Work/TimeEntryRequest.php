<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moveStatus', $this->route('task'));
    }

    public function rules(): array
    {
        return ['minutes_spent' => ['required', 'integer', 'min:5', 'max:1440'], 'work_date' => ['required', 'date', 'before_or_equal:today'], 'description' => ['nullable', 'string', 'max:500']];
    }
}
