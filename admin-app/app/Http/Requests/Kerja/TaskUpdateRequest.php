<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Ubah isi task. Deadline tidak bisa diubah langsung setelah baseline ada: lewat pengajuan perubahan jadwal. */
class TaskUpdateRequest extends FormRequest
{
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
            'priority' => ['required', Rule::in(array_keys(Kode::PRIORITY))],
            'task_type' => ['required', Rule::in(array_keys(Kode::TASK_TYPE))],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'due_at' => [$t->baseline_due_at ? 'prohibited' : 'nullable', 'date'],
            'platform' => ['nullable', 'string', 'max:80'],
            'scheduled_publish_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'publication_status' => ['nullable', Rule::in(array_keys(Kode::PUBLICATION_STATUS))],
            'publication_url' => ['nullable', 'url:https,http', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['due_at.prohibited' => 'Deadline sudah punya target awal. Ajukan perubahan jadwal supaya disetujui PIC atau manager.'];
    }
}
