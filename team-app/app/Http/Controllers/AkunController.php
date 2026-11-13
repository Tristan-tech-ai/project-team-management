<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use App\Support\Berkas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            // Disk bersama: foto yang diganti di Team App tampil juga di Admin App.
            $lama = DB::table('user_profiles')->where('user_id', $user->id)->value('avatar_path');
            $baru = $request->file('foto')->store('avatar', Berkas::DISK);
            if ($baru === false) {
                return back()->withErrors(['foto' => 'Foto gagal disimpan. Coba unggah lagi.']);
            }
            $isi['avatar_path'] = $baru;
            if ($lama && ($disk = Berkas::cari('public', $lama))) {
                $disk->delete($lama);
            }
        }
        DB::table('user_profiles')->updateOrInsert(['user_id' => $user->id], $isi + ['created_at' => now()]);

        return back()->with('ok', 'Profil tersimpan.');
    }

    /** Foto profil dari disk bersama (sama di kedua app); hanya pemiliknya dan Super Admin. */
    public function foto(Request $request, User $user): StreamedResponse
    {
        abort_unless((int) $request->user()->id === (int) $user->id || $request->user()->isSuperAdmin(), 403);
        $path = DB::table('user_profiles')->where('user_id', $user->id)->value('avatar_path');
        $disk = Berkas::cari('public', $path);
        abort_if($disk === null, 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
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
