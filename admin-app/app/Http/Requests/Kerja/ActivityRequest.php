<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Aktivitas operasional (Petunjuk Teknis 8.4): peserta harus anggota project, durasi >= 0. */
class ActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createActivity', $this->route('project'));
    }

    public function rules(): array
    {
        $pid = $this->route('project')->id;

        return [
            'activity_type' => ['required', Rule::in(array_keys(Kode::ACTIVITY_TYPE))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_keys(Kode::ACTIVITY_STATUS))],
            'scheduled_at' => ['nullable', 'date'],
            'occurred_at' => ['nullable', 'date', 'required_if:status,done'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'impact_type' => ['required', Rule::in(array_keys(Kode::IMPACT_TYPE))],
            'impact_notes' => ['nullable', 'string', 'max:2000'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $pid)->whereNull('deleted_at')],
            'participants' => ['array'], 'participants.*' => ['integer', Rule::exists('project_members', 'user_id')->where('project_id', $pid)->where('is_active', 1)],
            'ajukan_deadline' => ['nullable', 'date'],
            'alasan_deadline' => ['nullable', 'required_with:ajukan_deadline', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['participants.*.exists' => 'Peserta harus anggota aktif project ini.', 'occurred_at.required_if' => 'Isi tanggal kejadian untuk aktivitas yang sudah selesai.'];
    }
}
