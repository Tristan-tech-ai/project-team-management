<?php

namespace App\Http\Requests\Kerja;

use App\Support\Kode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Kirim hasil: tautan dan/atau berkas + satu catatan ringkas (Petunjuk Teknis 6.3 dan 9.2). */
class SubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'external_url' => ['nullable', 'url:https,http', 'max:1000', 'required_without:berkas'],
            'berkas' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,ppt,pptx,zip,mp4,txt,csv', 'required_without:external_url'],
            'submission_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['external_url.required_without' => 'Isi tautan hasil atau unggah berkas.', 'berkas.required_without' => 'Isi tautan hasil atau unggah berkas.'];
    }
}
