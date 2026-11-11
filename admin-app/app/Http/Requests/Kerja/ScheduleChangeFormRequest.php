<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleChangeFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // scope diperiksa controller (anggota/pengelola project).
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->whereNull('deleted_at')],
            'project_activity_id' => ['nullable', 'integer', 'exists:project_activities,id'],
            'proposed_due_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
