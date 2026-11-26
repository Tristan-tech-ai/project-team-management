<?php

namespace App\Http\Requests\Work;

use App\Support\Codes;
use App\Support\RichText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create/update a project (Technical Guide 8.1). PIC and members are checked by ProjectTeamService (active company members). */
class ProjectRequest extends FormRequest
{
    /** The description editor sends simple HTML (RichText allowlist); a code still filled in automatically is made unique on save. */
    protected function prepareForValidation(): void
    {
        $this->merge(['description' => RichText::clean($this->input('description'))]);
        if ($this->boolean('code_auto')) {
            $this->merge(['code' => null]);
        }
    }

    public function authorize(): bool
    {
        $p = $this->route('project');

        return $p ? $this->user()->can('update', $p) : $this->user()->can('create', \App\Models\Project::class);
    }

    public function rules(): array
    {
        $p = $this->route('project');
        $u = $this->user();
        $companies = $u->isSuperAdmin() ? null : $u->activeCompanyIds();

        return [
            'company_id' => [$p ? 'prohibited' : 'required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')->where('is_active', 1),
                function ($a, $v, $fail) use ($companies) { if ($companies !== null && ! in_array((int) $v, array_map('intval', $companies), true)) { $fail('Kamu hanya bisa membuat project di company tempatmu menjadi anggota.'); } }],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at'),
                // A client linked to other companies only (client_company) cannot be used here; general clients fit every company.
                function ($a, $v, $fail) use ($p) {
                    $company = (int) ($p ? $p->company_id : $this->input('company_id'));
                    $client = \App\Models\Client::query()->find((int) $v);
                    if ($company && $client && ! $client->isAvailableFor($company) && (int) ($p?->client_id) !== (int) $v) {
                        $fail('Client ini bukan client company yang dipilih. Pilih client lain, atau centang company-nya di Data Master, Client.');
                    }
                }],
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('projects', 'code')->ignore($p?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_type' => [$p ? 'prohibited' : 'required', Rule::in(array_keys(Codes::PROJECT_TYPE))],
            'status' => ['nullable', Rule::in(array_keys(Codes::PROJECT_STATUS))],
            'priority' => ['nullable', Rule::in(array_keys(Codes::PRIORITY))],
            'pic_user_id' => ['required', 'integer', 'exists:users,id'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'recurrence_unit' => ['nullable', Rule::in(array_keys(Codes::RECURRENCE_UNIT))],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:12'],
            'project_template_id' => [$p ? 'prohibited' : 'nullable', 'integer', Rule::exists('project_templates', 'id')->whereNull('deleted_at')->where('is_active', 1)],
            'divisions' => ['array'], 'divisions.*' => ['integer', 'exists:divisions,id'],
            'members' => ['array'], 'members.*' => ['integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'skip_past' => [$p ? 'prohibited' : 'nullable', 'boolean'],
            'result_optional' => ['nullable', 'boolean'],
        ];
    }
}
