<?php

namespace App\Http\Requests\Master;

use App\Services\Scope;
use App\Support\Links;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A client is a separate master record without a Client PIC (Technical Guide 03). */
class ClientRequest extends FormRequest
{
    /** U4: a code still filled in automatically from the name is made unique on save (the field shows the suggestion). */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('code_auto')) {
            $this->merge(['code' => null]);
        }
        if ($this->has('website')) {
            $this->merge(['website' => Links::normalize($this->input('website'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('clients.manage');
    }

    public function rules(): array
    {
        $id = $this->route('client')?->id;

        return [
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('clients', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url:http,https', Links::RULE, 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            // Companies that work with this client; none = general, every company may pick it (table client_company).
            'companies' => ['array'],
            'companies.*' => ['integer', Rule::exists('companies', 'id')->whereNull('deleted_at'),
                function ($a, $v, $fail) { if (! Scope::filterCompanies($this->user())->has((int) $v)) { $fail('Company itu di luar aksesmu.'); } }],
        ];
    }

    public function messages(): array
    {
        return ['website.url' => 'Alamat situs belum lengkap. Contoh: kopisenja.id', 'website.regex' => 'Alamat situs belum lengkap. Contoh: kopisenja.id'];
    }
}
