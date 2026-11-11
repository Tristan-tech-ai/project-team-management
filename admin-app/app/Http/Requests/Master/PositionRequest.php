<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Jabatan (positions) terpisah dari role (Petunjuk Teknis 4.1). */
class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('positions.manage');
    }

    public function rules(): array
    {
        $id = $this->route('position')?->id;

        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150', Rule::unique('positions', 'name')->where('company_id', $this->integer('company_id'))->ignore($id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }
}
