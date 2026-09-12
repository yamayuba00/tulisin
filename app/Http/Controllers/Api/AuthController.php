<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'password' => ['required', 'confirmed', Password::min(6)],
            'university' => ['nullable', 'string', 'max:191'],
            'interest' => ['nullable'],
            'ref' => ['nullable', 'string', 'max:40'],
        ])->validate();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'status' => 'active',
        ]);

        $role = Role::where('name', 'user')->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        if (! empty($data['university']) || ! empty($data['interest'])) {
            UserProfile::create([
                'user_id' => $user->id,
                'university' => $data['university'] ?? null,
                'major' => $this->normalizeInterest($data['interest'] ?? null),
            ]);
        }

        if (! empty($data['ref'])) {
            $this->processReferral((string) $data['ref'], $user);
        }

        $user->sendEmailVerificationNotification();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Registrasi berhasil. Email verifikasi telah dikirim.',
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ])->validate();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Akun dinonaktifkan.'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        Auth::guard('web')->login($user, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        return response()->json(['user' => $user ? $this->userPayload($user) : null]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ])->validate();

        $status = PasswordBroker::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __($status),
        ]);
    }

    /**
     * Reset password menggunakan token yang dikirim lewat email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ])->validate();

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            // Pesan generik agar tidak membedakan token salah vs email tidak terdaftar.
            return response()->json(['error' => 'Token reset tidak valid atau sudah kedaluwarsa.'], 422);
        }

        return response()->json(['message' => 'Password berhasil direset.']);
    }

    /**
     * Kirim ulang email verifikasi.
     */
    public function sendVerificationNotification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Email verifikasi telah dikirim.']);
    }

    /**
     * Lengkapi profil onboarding (kampus + kebutuhan) — utamanya untuk pengguna login sosial.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json(['message' => 'Tidak terautentikasi.'], 401);
        }

        $data = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'university' => ['nullable', 'string', 'max:191'],
            'interest' => ['nullable'],
            'nim' => ['nullable', 'string', 'max:40'],
            'degree' => ['nullable', 'string', 'max:20'],
            'ref' => ['nullable', 'string', 'max:40'],
        ])->validate();

        $user->phone = $data['phone'];
        $user->save();

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'university' => $data['university'] ?? null,
                'major' => $this->normalizeInterest($data['interest'] ?? null),
                'nim' => $data['nim'] ?? null,
                'degree' => $data['degree'] ?? null,
            ],
        );

        if (! empty($data['ref'])) {
            $this->processReferral((string) $data['ref'], $user);
        }

        $user->unsetRelation('profile');

        return response()->json([
            'message' => 'Profil berhasil disimpan.',
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Perbarui data akun (nama). Email sengaja tidak bisa diubah dari sini.
     */
    public function updateAccount(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json(['message' => 'Tidak terautentikasi.'], 401);
        }

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
        ])->validate();

        $user->name = $data['name'];
        $user->save();

        return response()->json([
            'message' => 'Akun berhasil diperbarui.',
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Ganti password — hanya untuk akun manual (login Google tidak punya password).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json(['message' => 'Tidak terautentikasi.'], 401);
        }

        if ($user->provider) {
            return response()->json(['message' => 'Akun login Google tidak bisa mengubah password.'], 403);
        }

        $data = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ])->validate();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->forceFill(['password' => $data['password']])->save();

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    /**
     * Verifikasi email dari tautan yang dikirim (redirect ke halaman SPA).
     */
    public function verifyEmail(Request $request): \Illuminate\Http\RedirectResponse
    {
        $fallback = rtrim((string) config('app.url'), '/') . '/verify-email';

        if (! $request->hasValidSignature()) {
            return redirect($fallback . '?status=error');
        }

        $user = User::find($request->route('id'));

        if (! $user || ! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return redirect($fallback . '?status=error');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($fallback . '?status=already');
        }

        $user->markEmailAsVerified();

        return redirect($fallback . '?status=success');
    }

    /**
     * Proses referral: catat pengguna baru ke perujuk dan beri +kredit per referral.
     * Gagal diam-diam (kode tidak valid / perujuk sudah ada) tidak menggagalkan registrasi.
     */
    private function processReferral(string $code, User $referredUser): void
    {
        $code = strtoupper(trim($code));

        $referralCode = ReferralCode::where('code', $code)->where('is_active', true)->first();
        if (! $referralCode || $referralCode->user_id === $referredUser->id) {
            return;
        }

        $referral = Referral::firstOrCreate(
            ['referred_user_id' => $referredUser->id],
            [
                'referrer_id' => $referralCode->user_id,
                'referral_code_id' => $referralCode->id,
                'status' => 'pending',
            ],
        );

        // Koin +20 tidak lagi diberikan otomatis.
        // Admin akan memverifikasi keaslian referral dulu sebelum menambah koin.
        if (! $referral->wasRecentlyCreated) {
            return;
        }
    }

    /**
     * Normalisasi nilai "kebutuhan" menjadi string (bisa array dari multi-select).
     *
     * @param  mixed  $value
     */
    private function normalizeInterest($value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        if ($user->isSuperAdmin()) {
            $permissions = Permission::pluck('name')->all();
        } else {
            $direct = $user->permissions()->pluck('name');
            $viaRoles = $user->roles()->with('permissions')->get()
                ->flatMap(fn ($role) => $role->permissions->pluck('name'));

            $permissions = $direct->merge($viaRoles)->unique()->values()->all();
        }

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'provider' => $user->provider,
            'email_verified' => $user->hasVerifiedEmail(),
            'is_super_admin' => $user->isSuperAdmin(),
            'profile' => [
                'university' => $user->profile?->university,
                'major' => $user->profile?->major,
                'nim' => $user->profile?->nim,
                'degree' => $user->profile?->degree,
            ],
            'roles' => $user->roles()->pluck('name')->all(),
            'permissions' => $permissions,
        ];
    }
}
