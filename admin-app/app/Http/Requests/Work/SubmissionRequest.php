<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
use App\Support\Links;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Submit a result: a link and/or a file + one short note (Technical Guide 6.3 and 9.2). */
class SubmissionRequest extends FormRequest
{
    /** "instagram.com/p/abc" is enough: the https:// is added here. */
    protected function prepareForValidation(): void
    {
        if ($this->has('external_url')) {
            $this->merge(['external_url' => Links::normalize($this->input('external_url'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('task'));
    }

    public function rules(): array
    {
        // "Hasil task boleh kosong" on the project: link and file are both optional (maintenance, research).
        $optional = (bool) $this->route('task')?->project?->resultOptional();

        return [
            'external_url' => ['nullable', 'url:https,http', Links::RULE, 'max:1000', ...($optional ? [] : ['required_without:file'])],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,ppt,pptx,zip,mp4,txt,csv', ...($optional ? [] : ['required_without:external_url'])],
            'submission_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['external_url.required_without' => 'Isi tautan hasil atau unggah berkas.', 'file.required_without' => 'Isi tautan hasil atau unggah berkas.',
            'external_url.url' => Links::MESSAGE, 'external_url.regex' => Links::MESSAGE];
    }
}
