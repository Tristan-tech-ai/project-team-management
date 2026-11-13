<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Submit a result: a link and/or a file + one short note (Technical Guide 6.3 and 9.2). */
class SubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'external_url' => ['nullable', 'url:https,http', 'max:1000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,ppt,pptx,zip,mp4,txt,csv', 'required_without:external_url'],
            'submission_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['external_url.required_without' => 'Isi tautan hasil atau unggah berkas.', 'file.required_without' => 'Isi tautan hasil atau unggah berkas.'];
    }
}
