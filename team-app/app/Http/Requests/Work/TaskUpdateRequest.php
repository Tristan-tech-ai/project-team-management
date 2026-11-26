<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
use App\Support\Links;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Update task content. Once a baseline exists the deadline cannot change directly: use a schedule change request. */
class TaskUpdateRequest extends FormRequest
{
    /** "instagram.com/p/abc" is enough: the https:// is added here. */
    protected function prepareForValidation(): void
    {
        if ($this->has('publication_url')) {
            $this->merge(['publication_url' => Links::normalize($this->input('publication_url'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        $t = $this->route('task');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(Codes::PRIORITY))],
            'task_type' => ['required', Rule::in(array_keys(Codes::TASK_TYPE))],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'due_at' => [$t->baseline_due_at ? 'prohibited' : 'nullable', 'date'],
            'platform' => ['nullable', 'string', 'max:80'],
            'scheduled_publish_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'publication_status' => ['nullable', Rule::in(array_keys(Codes::PUBLICATION_STATUS))],
            'publication_url' => ['nullable', 'url:https,http', Links::RULE, 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['due_at.prohibited' => 'Deadline sudah punya target awal. Ajukan perubahan jadwal supaya disetujui PIC atau manager.',
            'publication_url.url' => Links::MESSAGE, 'publication_url.regex' => Links::MESSAGE];
    }
}
