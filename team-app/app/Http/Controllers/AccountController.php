<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use App\Support\Files;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Profile & Password (Admin) and My Profile (Team): name, photo, contact, password, own permissions. */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('profile');

        return view('account.edit', [
            'user' => $user,
            'permissions' => $user->isSuperAdmin() ? Permission::query()->orderBy('id')->get() : Permission::query()->whereIn('slug', $user->permissionSlugs())->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $user = $request->user();
        $user->update(['name' => $data['name']]);
        $profile = ['phone' => $data['phone'] ?? null, 'updated_at' => now()];
        if ($request->hasFile('photo')) {
            // Shared disk: a photo changed in the Team App also shows in the Admin App.
            $old = DB::table('user_profiles')->where('user_id', $user->id)->value('avatar_path');
            $new = $request->file('photo')->store('avatar', Files::DISK);
            if ($new === false) {
                return back()->withErrors(['photo' => 'Foto gagal disimpan. Coba unggah lagi.']);
            }
            $profile['avatar_path'] = $new;
            if ($old && ($disk = Files::find('public', $old))) {
                $disk->delete($old);
            }
        }
        DB::table('user_profiles')->updateOrInsert(['user_id' => $user->id], $profile + ['created_at' => now()]);

        return back()->with('ok', 'Profil tersimpan.');
    }

    /** Profile photo from the shared disk (same in both apps); only its owner and the Super Admin. */
    public function photo(Request $request, User $user): StreamedResponse
    {
        abort_unless((int) $request->user()->id === (int) $user->id || $request->user()->isSuperAdmin(), 403);
        $path = DB::table('user_profiles')->where('user_id', $user->id)->value('avatar_path');
        $disk = Files::find('public', $path);
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
