<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('divisions.manage');
    }

    public function rules(): array
    {
        $id = $this->route('division')?->id;

        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150', Rule::unique('divisions', 'name')->where('company_id', $this->integer('company_id'))->ignore($id)],
            'lead_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /** Kepala divisi harus anggota aktif company yang sama. */
    public function after(): array
    {
        return [function (Validator $v) {
            $lead = $this->input('lead_user_id');
            if ($lead && ! DB::table('company_members')->where('company_id', $this->integer('company_id'))->where('user_id', $lead)->where('is_active', 1)->exists()) {
                $v->errors()->add('lead_user_id', 'Kepala divisi harus anggota aktif company ini.');
            }
        }];
    }
}
