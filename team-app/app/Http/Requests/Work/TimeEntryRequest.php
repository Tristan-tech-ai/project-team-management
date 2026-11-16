<?php

namespace App\Http\Requests\Work;

use App\Support\Dates;
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
        // "Today" is the day in the display time zone (Pengaturan Sistem), not in UTC: between 00.00 and 08.00 WITA the
        // UTC date is still yesterday, and the "Catat waktu kerja" buttons (which send the local date) were refused.
        $today = now(Dates::zone())->toDateString();

        return ['minutes_spent' => ['required', 'integer', 'min:5', 'max:1440'], 'work_date' => ['required', 'date', 'before_or_equal:'.$today], 'description' => ['nullable', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['work_date.before_or_equal' => 'Waktu kerja hanya bisa dicatat untuk hari ini atau tanggal sebelumnya.'];
    }

    public function attributes(): array
    {
        return ['work_date' => 'tanggal kerja', 'minutes_spent' => 'lama kerja'];
    }
}
