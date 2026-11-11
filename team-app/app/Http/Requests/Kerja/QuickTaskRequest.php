<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Quick Add Task (Petunjuk Teknis 6.3): cukup judul, PIC, pelaksana, deadline; status awal Antrian. */
class QuickTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createTask', $this->route('project'));
    }

    public function rules(): array
    {
        $pid = $this->route('project')->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'pic_user_id' => ['required', 'integer', 'exists:users,id'],
            'assignees' => ['array'], 'assignees.*' => ['integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'project_period_id' => ['nullable', 'integer', Rule::exists('project_periods', 'id')->where('project_id', $pid)],
            'priority' => ['nullable', Rule::in(array_keys(Kode::PRIORITY))],
            'task_type' => ['nullable', Rule::in(array_keys(Kode::TASK_TYPE))],
            'task_template_id' => ['nullable', 'integer', Rule::exists('task_templates', 'id')->whereNull('deleted_at')->where('is_active', 1)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
