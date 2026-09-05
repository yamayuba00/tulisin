<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\WriterProfile;
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
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'accountType' => ['sometimes', 'in:individual,agency'],
            'university' => ['nullable', 'string', 'max:191'],
            'agencyName' => ['nullable', 'string', 'max:191'],
            'interest' => ['nullable', 'string', 'max:191'],
            'ref' => ['nullable', 'string', 'max:40'],
        ])->validate();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
        ]);

        $roleName = ($data['accountType'] ?? 'individual') === 'agency' ? 'agency' : 'user';
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        if (! empty($data['university']) || ! empty($data['interest'])) {
            UserProfile::create([
                'user_id' => $user->id,
                'university' => $data['university'] ?? null,
                'major' => $data['interest'] ?? null,
            ]);
        }

        if ($roleName === 'agency' && ! empty($data['agencyName'])) {
            WriterProfile::create([
                'user_id' => $user->id,
                'agency_name' => $data['agencyName'],
            ]);
        }

        if (! empty($data['ref'])) {
            $this->processReferral((string) $data['ref'], $user);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
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

        Auth::guard('web')->login($user);
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
        return response()->json(['user' => $this->userPayload($request->user())]);
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
            'password' => ['required', 'confirmed', Password::min(6)],
        ])->validate();

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json(['error' => __($status)], 422);
        }

        return response()->json(['message' => __($status)]);
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
            'email_verified' => $user->hasVerifiedEmail(),
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $user->roles()->pluck('name')->all(),
            'permissions' => $permissions,
        ];
    }
}
