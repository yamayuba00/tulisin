<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Arahkan pengguna ke halaman login provider (Google/GitHub).
     */
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Tangani callback dari provider: login akun lama atau buat akun baru.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return redirect('/login?social_error=callback_failed');
        }

        $socialId = $socialUser->getId();
        $email = $socialUser->getEmail();

        // 1) Akun sosial ini sudah pernah masuk → langsung login.
        $user = User::where('provider', $provider)
            ->where('provider_id', $socialId)
            ->first();

        if ($user) {
            if ($user->status !== 'active') {
                return redirect('/login?social_error=inactive');
            }

            $this->login($request, $user);

            return redirect()->intended($this->dashboardPath($user));
        }

        // 2) Email wajib ada agar bisa dijadikan identitas unik.
        if (! $email) {
            return redirect('/login?social_error=email_missing');
        }

        // 3) Cegah akun ganda: email sudah dipakai (baik daftar manual atau provider lain).
        if (User::where('email', $email)->exists()) {
            return redirect('/login?social_error=email_exists');
        }

        // 4) Buat akun baru (email dari provider dianggap terverifikasi).
        $user = User::create([
            'name' => $socialUser->getName() ?: ($socialUser->getNickname() ?: 'Pengguna'),
            'email' => $email,
            'password' => null,
            'avatar' => $socialUser->getAvatar(),
            'provider' => $provider,
            'provider_id' => $socialId,
            'status' => 'active',
        ]);

        $user->markEmailAsVerified();

        $role = Role::where('name', 'user')->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        $this->login($request, $user);

        // Pengguna baru diarahkan ke halaman onboarding.
        return redirect('/boarding');
    }

    private function login(Request $request, User $user): void
    {
        $user->forceFill(['last_login_at' => now()])->save();

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
    }

    private function dashboardPath(User $user): string
    {
        return $user->isSuperAdmin() ? '/apps/u/admin/dashboard' : '/apps/u/dashboard';
    }
}
