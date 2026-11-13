<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use App\Services\Scope;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/** Anggota tim = users + profil + role + keanggotaan company dan divisi (dengan jabatan per divisi). */
class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('members.manage');
    }

    public function rules(): array
    {
        $user = $this->route('member');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(12)->letters()->numbers()],
            'role' => ['required', Rule::exists('roles', 'slug'), function (string $a, mixed $v, \Closure $gagal) {
                if ($v === 'super_admin' && ! $this->user()->isSuperAdmin()) {
                    $gagal('Hanya Super Admin yang bisa memberi role Super Admin.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'companies' => ['array'],
            'companies.*' => ['integer', Rule::exists('companies', 'id')->whereNull('deleted_at'), Scope::aturanCompany($this->user())],
            'divisi' => ['array'],
            'divisi.*.ikut' => ['nullable', 'boolean'],
            'divisi.*.position_id' => ['nullable', 'integer', 'exists:positions,id'],
        ];
    }

    /** Divisi dan jabatan harus berasal dari company yang dipilih untuk anggota ini. */
    public function after(): array
    {
        return [function (Validator $v) {
            $companies = array_map('intval', (array) $this->input('companies', []));
            foreach ((array) $this->input('divisi', []) as $divisionId => $isi) {
                if (empty($isi['ikut'])) {
                    continue;
                }
                $div = DB::table('divisions')->where('id', (int) $divisionId)->first();
                if (! $div || ! in_array((int) $div->company_id, $companies, true)) {
                    $v->errors()->add('divisi', 'Divisi yang dipilih harus milik company anggota ini.');
                    break;
                }
                if (! empty($isi['position_id']) && ! DB::table('positions')->where('id', (int) $isi['position_id'])->where('company_id', $div->company_id)->exists()) {
                    $v->errors()->add('divisi', 'Jabatan harus dari company yang sama dengan divisinya.');
                    break;
                }
            }
            if ($this->route('member')?->id === $this->user()->id && $this->has('is_active') && ! $this->boolean('is_active')) {
                $v->errors()->add('is_active', 'Kamu tidak bisa menonaktifkan akunmu sendiri.');
            }
        }];
    }
}
