<?php

namespace App\Http\Requests\Work;

use App\Services\TaskBulkService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** I13 bulk actions on the task list. Each task is checked again by TaskBulkService (policy and status rules). */
class BulkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tasks.assign') || $this->user()->hasPermission('tasks.move_status');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(array_keys(TaskBulkService::ACTIONS))],
            'user_id' => ['nullable', 'required_unless:action,start', 'integer', Rule::exists('users', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu task.',
            'ids.max' => 'Paling banyak 100 task sekali jalan.',
            'user_id.required_unless' => 'Pilih orangnya dulu.',
        ];
    }
}
