<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Profil & Password (Admin) dan Profil Saya (Tim): nama, foto, kontak, kata sandi, hak akses sendiri. */
class AkunController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('profile');

        return view('akun.edit', [
            'user' => $user,
            'izin' => $user->isSuperAdmin() ? Permission::query()->orderBy('id')->get() : Permission::query()->whereIn('slug', $user->permissionSlugs())->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $user = $request->user();
        $user->update(['name' => $data['name']]);
        $isi = ['phone' => $data['phone'] ?? null, 'updated_at' => now()];
        if ($request->hasFile('foto')) {
            $lama = DB::table('user_profiles')->where('user_id', $user->id)->value('avatar_path');
            $isi['avatar_path'] = $request->file('foto')->store('avatar', 'public');
            if ($lama && Storage::disk('public')->exists($lama)) {
                Storage::disk('public')->delete($lama);
            }
        }
        DB::table('user_profiles')->updateOrInsert(['user_id' => $user->id], $isi + ['created_at' => now()]);

        return back()->with('ok', 'Profil tersimpan.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        $request->user()->update(['password' => $request->input('password')]);

        return back()->with('ok', 'Kata sandi diganti.');
    }
}
