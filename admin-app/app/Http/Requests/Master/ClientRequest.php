<?php

namespace App\Http\Requests\Master;

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
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }
}
